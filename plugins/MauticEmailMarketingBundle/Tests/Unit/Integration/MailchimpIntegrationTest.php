<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Tests\Unit\Integration;

use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration;
use PHPUnit\Framework\TestCase;

final class MailchimpIntegrationTest extends TestCase
{
    private MailchimpIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integration = new MailchimpIntegration();
    }

    public function testGetNameReturnsMailchimp(): void
    {
        $this->assertSame('Mailchimp', $this->integration->getName());
    }

    public function testGetDisplayNameReturnsMailChimp(): void
    {
        $this->assertSame('MailChimp', $this->integration->getDisplayName());
    }

    public function testGetIconReturnsExpectedPath(): void
    {
        $this->assertSame('plugins/MauticEmailMarketingBundle/Assets/img/mailchimp.png', $this->integration->getIcon());
    }

    public function testGetApiKeyAndIsConfiguredWhenUnset(): void
    {
        $this->assertNull($this->integration->getApiKey());
        $this->assertFalse($this->integration->isConfigured());
    }

    public function testGetApiKeyAndIsConfiguredWhenSet(): void
    {
        $configuration = new Integration();
        $configuration->setApiKeys(['apikey' => 'abc-us21']);
        $this->integration->setIntegrationConfiguration($configuration);

        $this->assertSame('abc-us21', $this->integration->getApiKey());
        $this->assertTrue($this->integration->isConfigured());
    }
}
