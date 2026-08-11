<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit;

use EilingIo\SyliusTopiPlugin\PaymentSettlement;
use EilingIo\SyliusTopiPlugin\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{PaymentStatus, PaymentSettlement}>
     */
    public static function statuses(): iterable
    {
        yield 'offer created' => [PaymentStatus::OFFER_CREATED, PaymentSettlement::PENDING];
        yield 'offer pending review' => [PaymentStatus::OFFER_PENDING_REVIEW, PaymentSettlement::PENDING];
        yield 'offer accepted' => [PaymentStatus::OFFER_ACCEPTED, PaymentSettlement::PENDING];
        yield 'order created' => [PaymentStatus::ORDER_CREATED, PaymentSettlement::CAPTURED];
        yield 'order confirmed' => [PaymentStatus::ORDER_CONFIRMED, PaymentSettlement::CAPTURED];
        yield 'order completed' => [PaymentStatus::ORDER_COMPLETED, PaymentSettlement::CAPTURED];
        yield 'offer declined' => [PaymentStatus::OFFER_DECLINED, PaymentSettlement::FAILED];
        yield 'order rejected' => [PaymentStatus::ORDER_REJECTED, PaymentSettlement::FAILED];
        yield 'offer expired' => [PaymentStatus::OFFER_EXPIRED, PaymentSettlement::CANCELED];
        yield 'offer voided' => [PaymentStatus::OFFER_VOIDED, PaymentSettlement::CANCELED];
        yield 'order canceled' => [PaymentStatus::ORDER_CANCELED, PaymentSettlement::CANCELED];
    }

    #[DataProvider('statuses')]
    public function testSettlement(PaymentStatus $status, PaymentSettlement $expected): void
    {
        self::assertSame($expected, $status->settlement());
    }

    public function testTryFromAnUnknownValueReturnsNull(): void
    {
        self::assertNull(PaymentStatus::tryFrom('not_a_real_status'));
    }
}
