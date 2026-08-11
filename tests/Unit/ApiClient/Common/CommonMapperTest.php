<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Common;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\CommonMapper;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use PHPUnit\Framework\TestCase;

final class CommonMapperTest extends TestCase
{
    public function testMoneyAmountToArray(): void
    {
        $amount = new MoneyAmount();
        $amount->currency = 'EUR';
        $amount->gross = 1000;
        $amount->net = 840;

        self::assertSame([
            'currency' => 'EUR',
            'gross' => 1000,
            'net' => 840,
        ], CommonMapper::moneyAmountToArray($amount));
    }

    public function testMoneyAmountFromArray(): void
    {
        $amount = CommonMapper::moneyAmountFromArray([
            'currency' => 'EUR',
            'gross' => 1000,
            'net' => 840,
        ]);

        self::assertSame('EUR', $amount->currency);
        self::assertSame(1000, $amount->gross);
        self::assertSame(840, $amount->net);
    }

    public function testMoneyAmountFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $amount = CommonMapper::moneyAmountFromArray([]);

        self::assertFalse(isset($amount->currency));
        self::assertFalse(isset($amount->gross));
        self::assertFalse(isset($amount->net));
    }

    public function testProductReferenceToArray(): void
    {
        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = 'MUG-001';

        self::assertSame([
            'source' => 'syliusordernumbers',
            'reference' => 'MUG-001',
        ], CommonMapper::productReferenceToArray($reference));
    }

    public function testProductReferenceFromArray(): void
    {
        $reference = CommonMapper::productReferenceFromArray([
            'source' => 'syliusordernumbers',
            'reference' => 'MUG-001',
        ]);

        self::assertSame('syliusordernumbers', $reference->source);
        self::assertSame('MUG-001', $reference->reference);
    }

    public function testProductSummaryFromArray(): void
    {
        $summary = CommonMapper::productSummaryFromArray([
            'id' => 'summary-1',
            'is_supported' => true,
            'seller_product_reference' => [
                'source' => 'syliusordernumbers',
                'reference' => 'MUG-001',
            ],
            'available_contract_terms' => [
                'can_pay_now' => true,
                'can_rent' => true,
                'rent' => ['duration' => 12],
            ],
        ]);

        self::assertSame('summary-1', $summary->id);
        self::assertTrue($summary->isSupported);
        self::assertSame('MUG-001', $summary->sellerProductReference->reference);
        self::assertTrue($summary->availableContractTerms->canPayNow);
        self::assertTrue($summary->availableContractTerms->canRent);
        self::assertSame(12, $summary->availableContractTerms->rent->duration);
    }

    public function testProductSummaryFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $summary = CommonMapper::productSummaryFromArray([]);

        self::assertFalse(isset($summary->id));
        self::assertFalse(isset($summary->isSupported));
        self::assertFalse(isset($summary->availableContractTerms));
        self::assertFalse(isset($summary->sellerProductReference));
    }

    public function testContractTermsSummaryFromArrayWithoutRentDuration(): void
    {
        $summary = CommonMapper::contractTermsSummaryFromArray([
            'can_pay_now' => false,
            'can_rent' => true,
            'rent' => [],
        ]);

        self::assertFalse($summary->canPayNow);
        self::assertTrue($summary->canRent);
        self::assertNull($summary->rent->duration);
    }

    public function testContractTermsSummaryFromArrayWithoutRentKeyAtAll(): void
    {
        $summary = CommonMapper::contractTermsSummaryFromArray([]);

        self::assertNull($summary->canPayNow);
        self::assertNull($summary->canRent);
        self::assertFalse(isset($summary->rent));
    }
}
