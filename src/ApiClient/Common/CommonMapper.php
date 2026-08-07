<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Common;

class CommonMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function moneyAmountToArray(MoneyAmount $amount): array
    {
        return [
            'currency' => $amount->currency,
            'gross' => $amount->gross,
            'net' => $amount->net,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function applyMoneyAmount(MoneyAmount $amount, array $data): void
    {
        if (isset($data['currency'])) {
            $amount->currency = $data['currency'];
        }
        if (isset($data['gross'])) {
            $amount->gross = $data['gross'];
        }
        if (isset($data['net'])) {
            $amount->net = $data['net'];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function moneyAmountFromArray(array $data): MoneyAmount
    {
        $amount = new MoneyAmount();
        self::applyMoneyAmount($amount, $data);

        return $amount;
    }

    /**
     * @return array<string, mixed>
     */
    public static function productReferenceToArray(ProductReference $reference): array
    {
        return [
            'source' => $reference->source,
            'reference' => $reference->reference,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function productReferenceFromArray(array $data): ProductReference
    {
        $reference = new ProductReference();
        if (isset($data['source'])) {
            $reference->source = $data['source'];
        }
        if (isset($data['reference'])) {
            $reference->reference = $data['reference'];
        }

        return $reference;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function productSummaryFromArray(array $data): ProductSummary
    {
        $summary = new ProductSummary();
        if (isset($data['available_contract_terms'])) {
            $summary->availableContractTerms = self::contractTermsSummaryFromArray($data['available_contract_terms']);
        }
        if (isset($data['id'])) {
            $summary->id = $data['id'];
        }
        if (isset($data['is_supported'])) {
            $summary->isSupported = $data['is_supported'];
        }
        if (isset($data['seller_product_reference'])) {
            $summary->sellerProductReference = self::productReferenceFromArray($data['seller_product_reference']);
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function contractTermsSummaryFromArray(array $data): ContractTermsSummary
    {
        $summary = new ContractTermsSummary();
        if (isset($data['can_pay_now'])) {
            $summary->canPayNow = $data['can_pay_now'];
        }
        if (isset($data['can_rent'])) {
            $summary->canRent = $data['can_rent'];
        }
        if (isset($data['rent'])) {
            $rent = new ProductRentContract();
            $rent->duration = $data['rent']['duration'] ?? null;
            $summary->rent = $rent;
        }

        return $summary;
    }
}
