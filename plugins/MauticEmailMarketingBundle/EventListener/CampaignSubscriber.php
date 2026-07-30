<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailMarketingBundle\Connection\MailchimpClient;
use MauticPlugin\MauticEmailMarketingBundle\Integration\MailchimpIntegration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Replaces the legacy PluginBundle "plugin.leadpush" campaign action for Mailchimp. That legacy
 * action only resolves AbstractIntegration objects, so a migrated BasicIntegration is invisible to
 * it — the plugin must ship its own action.
 *
 * Pilot simplification: the destination list + opt-in options come from the integration's
 * featureSettings (configured once), and the contact is mapped with Mailchimp's default merge tags
 * (EMAIL/FNAME/LNAME). A configurable per-field mapping UI is a follow-up.
 */
final readonly class CampaignSubscriber implements EventSubscriberInterface
{
    public const ON_CAMPAIGN_BATCH_ACTION = 'mautic.emailmarketing.mailchimp.on_campaign_batch_action';

    public function __construct(
        private IntegrationsHelper $integrationsHelper,
        private MailchimpClient $mailchimpClient,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            self::ON_CAMPAIGN_BATCH_ACTION    => ['onCampaignTriggerAction', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addAction('mailchimp.push_lead', [
            'label'          => 'mautic.emailmarketing.mailchimp.campaign.push_lead',
            'description'    => 'mautic.emailmarketing.mailchimp.campaign.push_lead_descr',
            'batchEventName' => self::ON_CAMPAIGN_BATCH_ACTION,
        ]);
    }

    public function onCampaignTriggerAction(PendingEvent $event): void
    {
        try {
            $integration = $this->integrationsHelper->getIntegration(MailchimpIntegration::NAME);
        } catch (IntegrationNotFoundException) {
            $event->passAllWithError($this->translator->trans('mautic.emailmarketing.mailchimp.error.not_found'));

            return;
        }

        $configuration = $integration->getIntegrationConfiguration();
        if (!$configuration->getIsPublished()) {
            $event->passAllWithError($this->translator->trans('mautic.emailmarketing.mailchimp.error.not_published'));

            return;
        }

        $settings = $configuration->getFeatureSettings()['integration'] ?? [];
        $listId   = $settings['list'] ?? null;
        if (empty($listId)) {
            $event->passAllWithError($this->translator->trans('mautic.emailmarketing.mailchimp.error.no_list'));

            return;
        }

        $doubleOptin = (bool) ($settings['doubleOptin'] ?? true);
        $sendWelcome = (bool) ($settings['sendWelcome'] ?? true);

        foreach ($event->getPending() as $log) {
            $this->pushContact($log, (string) $listId, $doubleOptin, $sendWelcome, $event);
        }
    }

    private function pushContact(LeadEventLog $log, string $listId, bool $doubleOptin, bool $sendWelcome, PendingEvent $event): void
    {
        $lead = $log->getLead();
        if (!$lead instanceof Lead || empty($lead->getEmail())) {
            $event->passWithError($log, $this->translator->trans('mautic.emailmarketing.mailchimp.error.no_email'));

            return;
        }

        $mergeFields = array_filter([
            'FNAME' => (string) $lead->getFirstname(),
            'LNAME' => (string) $lead->getLastname(),
        ], static fn (string $value): bool => '' !== $value);

        try {
            $this->mailchimpClient->subscribeLead($lead->getEmail(), $listId, $mergeFields, $doubleOptin, $sendWelcome);
            $event->pass($log);
        } catch (\Throwable $e) {
            $event->passWithError($log, $e->getMessage());
        }
    }
}
