<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailMarketingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<mixed>>
 */
final class MailchimpKeysType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'apikey',
            TextType::class,
            [
                'label'       => 'mautic.emailmarketing.mailchimp.apikey',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => [
                    'class'        => 'form-control',
                    'tooltip'      => 'mautic.emailmarketing.mailchimp.apikey.tooltip',
                    'autocomplete' => 'off',
                ],
                'required'    => true,
                'constraints' => [new NotBlank()],
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['integration']);
    }
}
