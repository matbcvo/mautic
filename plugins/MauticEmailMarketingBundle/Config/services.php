<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
        'Api',
    ];

    $services->load('MauticPlugin\\MauticEmailMarketingBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');
    // Legacy AbstractIntegration-based integrations (not yet migrated).
    $services->set('mautic.integration.constantcontact', MauticPlugin\MauticEmailMarketingBundle\Integration\ConstantContactIntegration::class);
    $services->alias(MauticPlugin\MauticEmailMarketingBundle\Integration\ConstantContactIntegration::class, 'mautic.integration.constantcontact');
    $services->set('mautic.integration.icontact', MauticPlugin\MauticEmailMarketingBundle\Integration\IcontactIntegration::class);
    $services->alias(MauticPlugin\MauticEmailMarketingBundle\Integration\IcontactIntegration::class, 'mautic.integration.icontact');

    // Mailchimp migrated to IntegrationsBundle BasicIntegration architecture.
    $services->alias('mautic.integration.mailchimp', MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration::class);
    $services->alias('mautic.integration.mailchimp.config', MauticPlugin\MauticEmailMarketingBundle\Integration\Support\ConfigSupport::class);
};
