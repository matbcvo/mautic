<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class MailchimpIntegration extends BasicIntegration implements BasicInterface
{
    public const NAME = 'Mailchimp';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return 'MailChimp';
    }

    public function getIcon(): string
    {
        return 'plugins/MauticEmailMarketingBundle/Assets/img/mailchimp.png';
    }

    /**
     * The Mailchimp API key (format: "xxxxxxxx-us21"); its "-dc" suffix is the data-center used to build the API base URL.
     */
    public function getApiKey(): ?string
    {
        $apiKeys = $this->getIntegrationSettings()?->getApiKeys() ?? [];

        return $apiKeys['apikey'] ?? null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }
}
