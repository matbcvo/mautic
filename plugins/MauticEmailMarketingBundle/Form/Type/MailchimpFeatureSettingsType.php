<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\MauticEmailMarketingBundle\Connection\MailchimpClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Feature-settings form embedded via ConfigSupport::getFeatureSettingsConfigFormName(); its output
 * is persisted into the Integration entity's featureSettings and read by the campaign push action.
 *
 * @extends AbstractType<array<mixed>>
 */
final class MailchimpFeatureSettingsType extends AbstractType
{
    public function __construct(
        private readonly MailchimpClient $mailchimpClient,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $error   = null;
        $choices = [];
        try {
            // listId => name; ChoiceType wants label => value, so flip.
            $choices = array_flip($this->mailchimpClient->getLists());
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $builder->add('list', ChoiceType::class, [
            'label'       => 'mautic.emailmarketing.list',
            'choices'     => $choices,
            'required'    => false,
            'placeholder' => 'mautic.core.form.chooseone',
            'attr'        => ['class' => 'form-control', 'tooltip' => 'mautic.emailmarketing.list.tooltip'],
        ]);

        $builder->add('doubleOptin', YesNoButtonGroupType::class, [
            'label' => 'mautic.mailchimp.double_optin',
        ]);

        $builder->add('sendWelcome', YesNoButtonGroupType::class, [
            'label' => 'mautic.emailmarketing.send_welcome',
        ]);

        if (null !== $error) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($error): void {
                $event->getForm()->get('list')->addError(new FormError($error));
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['integration']);
    }

    public function getBlockPrefix(): string
    {
        return 'emailmarketing_mailchimp_feature_settings';
    }
}
