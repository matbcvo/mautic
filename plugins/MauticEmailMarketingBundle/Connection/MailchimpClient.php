<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Connection;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * New-architecture Mailchimp API client. Replaces the legacy Api/MailchimpApi which depended on
 * AbstractIntegration::makeRequest()/getKeys(). Uses the persisted integration API key directly.
 */
class MailchimpClient
{
    public function __construct(
        private readonly IntegrationsHelper $integrationsHelper,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string> listId => name
     */
    public function getLists(): array
    {
        $response = $this->request('GET', 'lists', ['count' => 100]);

        $choices = [];
        foreach ($response['lists'] ?? [] as $list) {
            $choices[$list['id']] = $list['name'];
        }

        asort($choices);

        return $choices;
    }

    /**
     * @return array<string, string> mergeFieldTag => name
     */
    public function getMergeFields(string $listId): array
    {
        $response = $this->request('GET', sprintf('lists/%s/merge-fields', $listId), ['count' => 100]);

        $fields = ['EMAIL' => 'Email Address'];
        foreach ($response['merge_fields'] ?? [] as $field) {
            $fields[$field['tag']] = $field['name'];
        }

        return $fields;
    }

    /**
     * Subscribe (or update) a contact on a Mailchimp list.
     *
     * @param array<string, mixed> $mergeFields keyed by Mailchimp merge tag (EMAIL excluded)
     */
    public function subscribeLead(string $email, string $listId, array $mergeFields, bool $doubleOptin, bool $sendWelcome): void
    {
        $this->request('POST', sprintf('lists/%s/members', $listId), [], [
            'email_address' => $email,
            'status'        => $doubleOptin ? 'pending' : 'subscribed',
            'merge_fields'  => (object) $mergeFields,
            'send_welcome'  => $sendWelcome,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $json
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $query = [], array $json = []): array
    {
        $apiKey = $this->getApiKey();
        $dc     = $this->extractDataCenter($apiKey);
        $url    = sprintf('https://%s.api.mailchimp.com/3.0/%s', $dc, $endpoint);

        $options = [
            'auth_basic' => ['mautic', $apiKey],
            'query'      => $query,
        ];
        if ([] !== $json) {
            $options['json'] = $json;
        }

        try {
            return $this->httpClient->request($method, $url, $options)->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Mailchimp API error: '.$e->getMessage());

            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function getApiKey(): string
    {
        try {
            /** @var MailchimpIntegration $integration */
            $integration = $this->integrationsHelper->getIntegration(MailchimpIntegration::NAME);
        } catch (IntegrationNotFoundException $e) {
            throw new \RuntimeException('Mailchimp integration is not available.', 0, $e);
        }

        $apiKey = $integration->getApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('Mailchimp API key is not configured.');
        }

        return $apiKey;
    }

    private function extractDataCenter(string $apiKey): string
    {
        $parts = explode('-', $apiKey);
        if (2 !== count($parts) || '' === $parts[1]) {
            throw new \RuntimeException('Invalid Mailchimp API key: missing data-center suffix.');
        }

        return $parts[1];
    }
}
