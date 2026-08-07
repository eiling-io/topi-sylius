<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

#[AutoconfigureTag('sylius.gateway_configuration_type', [
    'type' => 'topi_payment',
    'label' => 'Topi Payment',
])]
class GatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientId', TextType::class, [
                'required' => true,
                'label' => 'Client ID',
            ])
            ->add('clientSecret', PasswordType::class, [
                'required' => true,
                'label' => 'Client Secret',
            ])
            ->add('enableLive', CheckboxType::class, [
                'required' => false,
                'label' => 'Live-Modus aktivieren',
            ])
            ->add('webhookSigningSecrets', TextType::class, [
                'required' => false,
                'label' => 'Webhook Signing Secrets (kommagetrennt)',
            ])
            ->add('enableWebhookSignatureChecks', CheckboxType::class, [
                'required' => false,
                'label' => 'Webhook-Signaturprüfung aktivieren',
            ])
        ;
    }
}
