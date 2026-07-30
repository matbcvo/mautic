<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\MauticEmailMarketingBundle\Integration\Support\ConfigSupport;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Routing\RouterInterface;

final class MailchimpConfigControllerTest extends MauticMysqlTestCase
{
    private const API_KEY = 'test-key-us21';

    private string $configRoute;

    protected function setUp(): void
    {
        parent::setUp();

        $plugin = new Plugin();
        $plugin->setName('Email Marketing');
        $plugin->setBundle('MauticEmailMarketingBundle');
        $this->em->persist($plugin);

        $integration = new Integration();
        $integration->setName('Mailchimp');
        $integration->setIsPublished(false);
        $integration->setPlugin($plugin);
        $this->em->persist($integration);

        $this->em->flush();

        $this->configRoute = static::getContainer()->get(RouterInterface::class)->generate('mautic_plugin_config', ['name' => 'Mailchimp']);
    }

    public function testConfigFormRendersAuthAndFeatureSettingsFields(): void
    {
        // No API key set -> the feature-settings list gracefully renders empty (no live API call).
        $crawler = $this->client->request('GET', $this->configRoute);
        $this->assertResponseIsSuccessful();

        // Auth tab: the API key field from MailchimpKeysType.
        $this->assertCount(1, $crawler->filter('#integration_config_apiKeys_apikey'));

        // Features tab: the list picker from MailchimpFeatureSettingsType (embedded under featureSettings.integration).
        $this->assertCount(1, $crawler->filter('#integration_config_featureSettings_integration_list'));
    }

    public function testSavedApiKeyIsPublishedEncryptedAtRestAndDecryptsBack(): void
    {
        $crawler = $this->client->request('GET', $this->configRoute);
        $this->assertResponseIsSuccessful();

        $form             = $crawler->selectButton('Save & Close')->form();
        $isPublishedField = $form['integration_config[isPublished]'];
        $this->assertInstanceOf(ChoiceFormField::class, $isPublishedField);
        $isPublishedField->select('1');
        $form['integration_config[apiKeys][apikey]']->setValue(self::API_KEY);

        $this->client->submit($form);
        $this->assertResponseIsSuccessful();

        $this->em->clear();

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'Mailchimp']);
        $this->assertInstanceOf(Integration::class, $integration);
        $this->assertTrue($integration->getIsPublished());
        $this->assertNotSame(self::API_KEY, $integration->getApiKeys()['apikey'] ?? null);

        /** @var IntegrationsHelper $integrationsHelper */
        $integrationsHelper = static::getContainer()->get(IntegrationsHelper::class);
        /** @var ConfigSupport $configSupport */
        $configSupport = static::getContainer()->get(ConfigSupport::class);
        $decrypted     = $integrationsHelper->getIntegrationConfiguration($configSupport);
        $this->assertSame(self::API_KEY, $decrypted->getApiKeys()['apikey']);
    }
}
