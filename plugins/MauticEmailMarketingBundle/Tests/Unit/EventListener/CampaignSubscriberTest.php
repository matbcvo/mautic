<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Tests\Unit\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\MauticEmailMarketingBundle\Connection\MailchimpClient;
use MauticPlugin\MauticEmailMarketingBundle\EventListener\CampaignSubscriber;
use MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CampaignSubscriberTest extends TestCase
{
    private IntegrationsHelper&MockObject $integrationsHelper;

    private MailchimpClient&MockObject $mailchimpClient;

    private CampaignSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integrationsHelper = $this->createMock(IntegrationsHelper::class);
        $this->mailchimpClient    = $this->createMock(MailchimpClient::class);
        $translator               = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->subscriber = new CampaignSubscriber($this->integrationsHelper, $this->mailchimpClient, $translator);
    }

    public function testOnCampaignBuildRegistersPushAction(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $event = new CampaignBuilderEvent($translator);

        $this->subscriber->onCampaignBuild($event);

        $actions = $event->getActions();
        $this->assertArrayHasKey('mailchimp.push_lead', $actions);
        $this->assertSame(CampaignSubscriber::ON_CAMPAIGN_BATCH_ACTION, $actions['mailchimp.push_lead']['batchEventName']);
    }

    public function testTriggerFailsAllWhenNoListConfigured(): void
    {
        $this->integrationsHelper->method('getIntegration')->willReturn($this->makeIntegration(true, []));

        $event = $this->createMock(PendingEvent::class);
        $event->expects($this->once())->method('passAllWithError');
        $event->expects($this->never())->method('getPending');

        $this->subscriber->onCampaignTriggerAction($event);
    }

    public function testTriggerPushesConfiguredContact(): void
    {
        $this->integrationsHelper->method('getIntegration')->willReturn(
            $this->makeIntegration(true, ['integration' => ['list' => 'list-1', 'doubleOptin' => false, 'sendWelcome' => false]])
        );

        $lead = new Lead();
        $lead->setEmail('jane@example.com');
        $lead->setFirstname('Jane');

        $log = $this->createMock(LeadEventLog::class);
        $log->method('getLead')->willReturn($lead);

        $this->mailchimpClient->expects($this->once())
            ->method('subscribeLead')
            ->with('jane@example.com', 'list-1', ['FNAME' => 'Jane'], false, false);

        $event = $this->createMock(PendingEvent::class);
        $event->method('getPending')->willReturn(new ArrayCollection([$log]));
        $event->expects($this->once())->method('pass')->with($log);

        $this->subscriber->onCampaignTriggerAction($event);
    }

    /**
     * @param array<string, mixed> $featureSettings
     */
    private function makeIntegration(bool $published, array $featureSettings): MailchimpIntegration
    {
        $configuration = new Integration();
        $configuration->setIsPublished($published);
        $configuration->setFeatureSettings($featureSettings);

        $integration = new MailchimpIntegration();
        $integration->setIntegrationConfiguration($configuration);

        return $integration;
    }
}
