<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Tests\Unit\Integration\Support;

use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeaturesInterface;
use MauticPlugin\MauticEmailMarketingBundle\Form\Type\MailchimpFeatureSettingsType;
use MauticPlugin\MauticEmailMarketingBundle\Form\Type\MailchimpKeysType;
use MauticPlugin\MauticEmailMarketingBundle\Integration\Support\ConfigSupport;
use PHPUnit\Framework\TestCase;

final class ConfigSupportTest extends TestCase
{
    private ConfigSupport $configSupport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSupport = new ConfigSupport();
    }

    public function testGetAuthConfigFormNameReturnsKeysType(): void
    {
        $this->assertSame(MailchimpKeysType::class, $this->configSupport->getAuthConfigFormName());
    }

    public function testGetFeatureSettingsConfigFormNameReturnsFeatureSettingsType(): void
    {
        $this->assertSame(MailchimpFeatureSettingsType::class, $this->configSupport->getFeatureSettingsConfigFormName());
    }

    public function testGetSupportedFeaturesDeclaresPushLeadAsSlugKeyedPair(): void
    {
        $this->assertSame(
            [ConfigFormFeaturesInterface::FEATURE_PUSH_LEAD => 'mautic.integration.form.feature.push_lead'],
            $this->configSupport->getSupportedFeatures()
        );
    }
}
