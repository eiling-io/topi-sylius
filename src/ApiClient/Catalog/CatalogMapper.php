<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\CommonMapper;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;

use function array_map;

class CatalogMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function catalogProductToArray(CatalogProduct $product): array
    {
        $data = [
            'title' => $product->title,
            'description' => $product->description,
            'is_active' => $product->isActive,
        ];

        if ($product->subtitle !== null) {
            $data['subtitle'] = $product->subtitle;
        }
        if ($product->descriptionLines !== []) {
            $data['description_lines'] = $product->descriptionLines;
        }
        if ($product->price !== null) {
            $data['price'] = self::moneyToArray($product->price);
        }
        if ($product->manufacturer !== null) {
            $data['manufacturer'] = $product->manufacturer;
        }
        if ($product->productStandardIdentifiers !== []) {
            $data['product_standard_identifiers'] = array_map(self::productIdentifierToArray(...), $product->productStandardIdentifiers);
        }
        if ($product->sellerCategories !== []) {
            $data['seller_categories'] = array_map(self::categoryToArray(...), $product->sellerCategories);
        }
        if ($product->sellerProductReferences !== []) {
            $data['seller_product_references'] = array_map(CommonMapper::productReferenceToArray(...), $product->sellerProductReferences);
        }
        if ($product->shopProductDescriptionUrl !== null) {
            $data['shop_product_description_url'] = $product->shopProductDescriptionUrl;
        }
        if ($product->extraDetails !== []) {
            $data['extra_details'] = array_map(self::extraProductDetailsToArray(...), $product->extraDetails);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function pricingRequestToArray(PricingRequest $request): array
    {
        return [
            'price' => self::moneyToArray($request->price),
            'seller_product_reference' => CommonMapper::productReferenceToArray($request->sellerProductReference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function recommendedRentalPricingRequestToArray(RecommendedRentalPricingRequest $request): array
    {
        $data = [
            'seller_product_reference' => CommonMapper::productReferenceToArray($request->sellerProductReference),
        ];

        if ($request->price !== null) {
            $data['price'] = self::moneyToArray($request->price);
        }
        if ($request->baseRentalPrice !== null) {
            $data['base_rental_price'] = self::moneyToArray($request->baseRentalPrice);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function recommendedRentalPricingDetailsFromArray(array $data): RecommendedRentalPricingDetails
    {
        $details = new RecommendedRentalPricingDetails();

        if (isset($data['seller_product_reference'])) {
            $details->sellerProductReference = CommonMapper::productReferenceFromArray($data['seller_product_reference']);
        }
        if (isset($data['has_rental_terms'])) {
            $details->hasRentalTerms = $data['has_rental_terms'];
        }
        if (isset($data['currency'])) {
            $details->currency = $data['currency'];
        }
        if (isset($data['monthly_rental_amount'])) {
            $details->monthlyRentalAmount = $data['monthly_rental_amount'];
        }
        if (isset($data['monthly_rental_terms'])) {
            $details->monthlyRentalTerms = self::rentContractTermFromArray($data['monthly_rental_terms']);
        }
        if (isset($data['summary'])) {
            $details->summary = $data['summary'];
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function calculatePricingResponseFromArray(array $data): CalculatePricingResponse
    {
        $response = new CalculatePricingResponse();

        if (isset($data['available_contract_types'])) {
            $response->availableContractTypes = $data['available_contract_types'];
        }
        if (isset($data['is_supported'])) {
            $response->isSupported = $data['is_supported'];
        }
        if (isset($data['pay_now'])) {
            $response->payNow = self::payNowContractTermFromArray($data['pay_now']);
        }
        if (isset($data['rent'])) {
            $response->rent = array_map(self::rentContractTermFromArray(...), $data['rent']);
        }
        if (isset($data['summary'])) {
            $response->summary = $data['summary'];
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function rentContractTermFromArray(array $data): RentContractTerm
    {
        $term = new RentContractTerm();

        if (isset($data['duration'])) {
            $term->duration = $data['duration'];
        }
        if (isset($data['id'])) {
            $term->id = $data['id'];
        }
        if (isset($data['monthly_amount'])) {
            $term->monthlyAmount = self::moneyWithRequiredTaxFromArray($data['monthly_amount']);
        }

        return $term;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function payNowContractTermFromArray(array $data): PayNowContractTerm
    {
        $term = new PayNowContractTerm();

        if (isset($data['id'])) {
            $term->id = $data['id'];
        }
        if (isset($data['amount'])) {
            $term->amount = self::moneyWithRequiredTaxFromArray($data['amount']);
        }

        return $term;
    }

    /**
     * @return array<string, mixed>
     */
    private static function moneyToArray(MoneyAmount $amount): array
    {
        $data = CommonMapper::moneyAmountToArray($amount);
        if ($amount instanceof MoneyAmountWithOptionalTax && $amount->taxRate !== null) {
            $data['tax_rate'] = $amount->taxRate;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function moneyWithRequiredTaxFromArray(array $data): MoneyAmountWithRequiredTax
    {
        $amount = new MoneyAmountWithRequiredTax();
        CommonMapper::applyMoneyAmount($amount, $data);
        if (isset($data['tax_rate'])) {
            $amount->taxRate = $data['tax_rate'];
        }

        return $amount;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productIdentifierToArray(ProductIdentifier $identifier): array
    {
        return [
            'id' => $identifier->id,
            'identifier_type' => $identifier->identifierType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function categoryToArray(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'parent_category_id' => $category->parentCategoryId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function extraProductDetailsToArray(ExtraProductDetails $details): array
    {
        return [
            'property' => $details->property,
            'value' => $details->value,
        ];
    }
}
