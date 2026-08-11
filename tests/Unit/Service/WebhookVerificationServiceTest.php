<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use EilingIo\SyliusTopiPlugin\Service\WebhookVerificationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebhookVerificationServiceTest extends TestCase
{
    public function testVerifyJustDecodesThePayloadWhenSignatureChecksAreDisabled(): void
    {
        $service = new WebhookVerificationService('whsec_unused', false);

        $result = $service->verify('{"event":"order.created"}', []);

        self::assertSame(['event' => 'order.created'], $result);
    }

    public function testVerifyThrowsWhenNoConfiguredSecretMatches(): void
    {
        // Real signature verification (the success path) requires a payload signed
        // by an actual Svix private key, which isn't something to fabricate in a
        // unit test — this covers the failure path instead: every configured secret
        // is tried and none can verify an unsigned payload.
        $service = new WebhookVerificationService('whsec_aW52YWxpZA==,whsec_YWxzb2ludmFsaWQ=', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Webhook signature verification failed for all configured signing secrets.');

        $service->verify('{"event":"order.created"}', [
            'svix-id' => 'msg_1',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,not-a-real-signature',
        ]);
    }

    public function testVerifyThrowsWhenNoSecretsAreConfiguredAtAll(): void
    {
        $service = new WebhookVerificationService('', true);

        $this->expectException(RuntimeException::class);

        $service->verify('{"event":"order.created"}', [
            'svix-id' => 'msg_1',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,not-a-real-signature',
        ]);
    }
}
