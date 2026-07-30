<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Tests\Unit\Connection;

use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\MauticEmailMarketingBundle\Connection\MailchimpClient;
use MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailchimpClientTest extends TestCase
{
    private function makeClient(string $apiKey, HttpClientInterface $httpClient): MailchimpClient
    {
        $integration   = new MailchimpIntegration();
        $configuration = new Integration();
        $configuration->setApiKeys(['apikey' => $apiKey]);
        $integration->setIntegrationConfiguration($configuration);

        $integrationsHelper = $this->createMock(IntegrationsHelper::class);
        $integrationsHelper->method('getIntegration')->with(MailchimpIntegration::NAME)->willReturn($integration);

        return new MailchimpClient($integrationsHelper, $httpClient, $this->createStub(LoggerInterface::class));
    }

    public function testGetListsParsesAndSortsByName(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'lists' => [
                ['id' => 'id-b', 'name' => 'Bravo'],
                ['id' => 'id-a', 'name' => 'Alpha'],
            ],
        ]);

        $captured   = [];
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$captured, $response): ResponseInterface {
                $captured = [$method, $url, $options];

                return $response;
            });

        $result = $this->makeClient('abc-us21', $httpClient)->getLists();

        $this->assertSame(['id-a' => 'Alpha', 'id-b' => 'Bravo'], $result);
        $this->assertSame('GET', $captured[0]);
        $this->assertSame('https://us21.api.mailchimp.com/3.0/lists', $captured[1]);
        $this->assertSame(['mautic', 'abc-us21'], $captured[2]['auth_basic']);
        $this->assertSame(100, $captured[2]['query']['count']);
    }

    public function testSubscribeLeadPostsMemberPayload(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['id' => 'member-1']);

        $captured   = [];
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$captured, $response): ResponseInterface {
                $captured = [$method, $url, $options];

                return $response;
            });

        $this->makeClient('abc-us21', $httpClient)->subscribeLead('jane@example.com', 'list-1', ['FNAME' => 'Jane'], false, false);

        $this->assertSame('POST', $captured[0]);
        $this->assertSame('https://us21.api.mailchimp.com/3.0/lists/list-1/members', $captured[1]);
        $this->assertSame('jane@example.com', $captured[2]['json']['email_address']);
        $this->assertSame('subscribed', $captured[2]['json']['status']);
    }

    public function testInvalidApiKeyWithoutDataCenterThrows(): void
    {
        $client = $this->makeClient('no-datacenter-suffix-key', $this->createStub(HttpClientInterface::class));

        $this->expectException(\RuntimeException::class);

        $client->getLists();
    }
}
