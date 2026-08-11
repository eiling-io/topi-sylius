<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Common;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\ContractTermsSummary;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductSummary;
use PHPUnit\Framework\TestCase;

final class ProductSummaryTest extends TestCase
{
    public function testConvertToAvailableContractTermsListWithBothSupported(): void
    {
        $summary = new ProductSummary();
        $summary->availableContractTerms = $this->contractTerms(canRent: true, canPayNow: true);

        self::assertSame(['rent', 'pay_now'], $summary->convertToAvailableContractTermsList());
    }

    public function testConvertToAvailableContractTermsListWithOnlyRent(): void
    {
        $summary = new ProductSummary();
        $summary->availableContractTerms = $this->contractTerms(canRent: true, canPayNow: false);

        self::assertSame(['rent'], $summary->convertToAvailableContractTermsList());
    }

    public function testConvertToAvailableContractTermsListWithOnlyPayNow(): void
    {
        $summary = new ProductSummary();
        $summary->availableContractTerms = $this->contractTerms(canRent: false, canPayNow: true);

        self::assertSame(['pay_now'], $summary->convertToAvailableContractTermsList());
    }

    public function testConvertToAvailableContractTermsListWithNeitherSupported(): void
    {
        $summary = new ProductSummary();
        $summary->availableContractTerms = $this->contractTerms(canRent: false, canPayNow: false);

        self::assertSame([], $summary->convertToAvailableContractTermsList());
    }

    private function contractTerms(bool $canRent, bool $canPayNow): ContractTermsSummary
    {
        $terms = new ContractTermsSummary();
        $terms->canRent = $canRent;
        $terms->canPayNow = $canPayNow;

        return $terms;
    }
}
