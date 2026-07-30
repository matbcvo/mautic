<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormAuthInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeaturesInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\MauticEmailMarketingBundle\Form\Type\MailchimpFeatureSettingsType;
use MauticPlugin\MauticEmailMarketingBundle\Form\Type\MailchimpKeysType;
use MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration;

final class ConfigSupport extends MailchimpIntegration implements ConfigFormInterface, ConfigFormAuthInterface, ConfigFormFeaturesInterface, ConfigFormFeatureSettingsInterface
{
    use DefaultConfigFormTrait;

    public function getAuthConfigFormName(): string
    {
        return MailchimpKeysType::class;
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedFeatures(): array
    {
        return [
            ConfigFormFeaturesInterface::FEATURE_PUSH_LEAD => 'mautic.integration.form.feature.push_lead',
        ];
    }

    public function getFeatureSettingsConfigFormName(): string
    {
        return MailchimpFeatureSettingsType::class;
    }
}
