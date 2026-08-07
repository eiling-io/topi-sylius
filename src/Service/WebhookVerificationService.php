<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use JsonException;
use RuntimeException;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use const JSON_THROW_ON_ERROR;

class WebhookVerificationService
{
    public function __construct(
        #[Autowire(env: 'TOPI_WEBHOOK_SIGNING_SECRETS')]
        private readonly string $webhookSigningSecrets,
        #[Autowire(env: 'bool:TOPI_ENABLE_WEBHOOK_SIGNATURE_CHECKS')]
        private readonly bool $enableWebhookSignatureChecks,
    ) {
    }

    /**
     * @return string[]
     */
    protected function readWebhookSigningSecrets(): array
    {
        $signingSecretString = $this->webhookSigningSecrets;

        if ($signingSecretString === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $signingSecretString) as $item) {
            $result[] = trim($item);
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $headers
     * @throws RuntimeException
     * @throws JsonException
     * @return array<string, mixed>
     */
    public function verify(string $payload, array $headers): array
    {
        if (!$this->enableWebhookSignatureChecks) {
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        foreach ($this->readWebhookSigningSecrets() as $signingSecret) {
            $wh = new Webhook($signingSecret);

            try {
                return $wh->verify($payload, $headers);
            } catch (WebhookVerificationException $e) {
            }
        }

        throw new RuntimeException('Webhook signature verification failed for all configured signing secrets.');
    }
}
