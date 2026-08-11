<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\Category;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogMapper;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogProduct;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\ExtraProductDetails;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\MoneyAmountWithOptionalTax;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\PricingRequest;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\ProductIdentifier;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\RecommendedRentalPricingRequest;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use PHPUnit\Framework\TestCase;

final class CatalogMapperTest extends TestCase
{
    public function testCatalogProductToArrayWithOnlyRequiredFields(): void
    {
        $product = new CatalogProduct();
        $product->title = 'Topi Mug';
        $product->description = 'A mug.';
        $product->isActive = true;

        self::assertSame([
            'title' => 'Topi Mug',
            'description' => 'A mug.',
            'is_active' => true,
        ], CatalogMapper::catalogProductToArray($product));
    }

    public function testCatalogProductToArrayWithAllOptionalFields(): void
    {
        $product = new CatalogProduct();
        $product->title = 'Topi Mug';
        $product->subtitle = 'Now with handle';
        $product->description = 'A mug.';
        $product->descriptionLines = ['Line 1', 'Line 2'];
        $product->isActive = true;
        $product->manufacturer = 'Acme';
        $product->shopProductDescriptionUrl = 'https://shop.example/mug';

        $price = new MoneyAmountWithOptionalTax();
        $price->currency = 'EUR';
        $price->gross = 1000;
        $price->net = 840;
        $price->taxRate = 2000;
        $product->price = $price;

        $identifier = new ProductIdentifier();
        $identifier->id = '1234567890123';
        $identifier->identifierType = 'ean';
        $product->productStandardIdentifiers = [$identifier];

        $category = new Category();
        $category->id = 'mugs';
        $category->name = 'Mugs';
        $category->parentCategoryId = 'kitchen';
        $product->sellerCategories = [$category];

        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = 'MUG-001';
        $product->sellerProductReferences = [$reference];

        $extra = new ExtraProductDetails();
        $extra->property = 'color';
        $extra->value = 'blue';
        $product->extraDetails = [$extra];

        $data = CatalogMapper::catalogProductToArray($product);

        self::assertSame('Now with handle', $data['subtitle']);
        self::assertSame(['Line 1', 'Line 2'], $data['description_lines']);
        self::assertSame(['currency' => 'EUR', 'gross' => 1000, 'net' => 840, 'tax_rate' => 2000], $data['price']);
        self::assertSame('Acme', $data['manufacturer']);
        self::assertSame([['id' => '1234567890123', 'identifier_type' => 'ean']], $data['product_standard_identifiers']);
        self::assertSame([['id' => 'mugs', 'name' => 'Mugs', 'parent_category_id' => 'kitchen']], $data['seller_categories']);
        self::assertSame([['source' => 'syliusordernumbers', 'reference' => 'MUG-001']], $data['seller_product_references']);
        self::assertSame('https://shop.example/mug', $data['shop_product_description_url']);
        self::assertSame([['property' => 'color', 'value' => 'blue']], $data['extra_details']);
    }

    public function testPricingRequestToArray(): void
    {
        $price = new MoneyAmountWithOptionalTax();
        $price->currency = 'EUR';
        $price->gross = 1000;
        $price->net = 840;

        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = 'MUG-001';

        $request = new PricingRequest();
        $request->price = $price;
        $request->sellerProductReference = $reference;

        self::assertSame([
            'price' => ['currency' => 'EUR', 'gross' => 1000, 'net' => 840],
            'seller_product_reference' => ['source' => 'syliusordernumbers', 'reference' => 'MUG-001'],
        ], CatalogMapper::pricingRequestToArray($request));
    }

    public function testRecommendedRentalPricingRequestToArrayWithOnlyRequiredFields(): void
    {
        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = 'MUG-001';

        $request = new RecommendedRentalPricingRequest();
        $request->sellerProductReference = $reference;

        self::assertSame([
            'seller_product_reference' => ['source' => 'syliusordernumbers', 'reference' => 'MUG-001'],
        ], CatalogMapper::recommendedRentalPricingRequestToArray($request));
    }

    public function testRecommendedRentalPricingRequestToArrayWithPrices(): void
    {
        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = 'MUG-001';

        $price = new MoneyAmountWithOptionalTax();
        $price->currency = 'EUR';
        $price->gross = 1000;
        $price->net = 840;

        $baseRentalPrice = new MoneyAmountWithOptionalTax();
        $baseRentalPrice->currency = 'EUR';
        $baseRentalPrice->gross = 50;
        $baseRentalPrice->net = 42;

        $request = new RecommendedRentalPricingRequest();
        $request->sellerProductReference = $reference;
        $request->price = $price;
        $request->baseRentalPrice = $baseRentalPrice;

        $data = CatalogMapper::recommendedRentalPricingRequestToArray($request);

        self::assertSame(['currency' => 'EUR', 'gross' => 1000, 'net' => 840], $data['price']);
        self::assertSame(['currency' => 'EUR', 'gross' => 50, 'net' => 42], $data['base_rental_price']);
    }

    public function testRecommendedRentalPricingDetailsFromArray(): void
    {
        $details = CatalogMapper::recommendedRentalPricingDetailsFromArray([
            'seller_product_reference' => ['source' => 'syliusordernumbers', 'reference' => 'MUG-001'],
            'has_rental_terms' => true,
            'currency' => 'EUR',
            'monthly_rental_amount' => 500,
            'monthly_rental_terms' => [
                'duration' => 12,
                'id' => 'term-1',
                'monthly_amount' => ['currency' => 'EUR', 'gross' => 500, 'net' => 420, 'tax_rate' => 2000],
            ],
            'summary' => 'Ab 5€/Monat',
        ]);

        self::assertSame('MUG-001', $details->sellerProductReference->reference);
        self::assertTrue($details->hasRentalTerms);
        self::assertSame('EUR', $details->currency);
        self::assertSame(500, $details->monthlyRentalAmount);
        self::assertSame(12, $details->monthlyRentalTerms->duration);
        self::assertSame('term-1', $details->monthlyRentalTerms->id);
        self::assertSame(2000, $details->monthlyRentalTerms->monthlyAmount->taxRate);
        self::assertSame('Ab 5€/Monat', $details->summary);
    }

    public function testRecommendedRentalPricingDetailsFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $details = CatalogMapper::recommendedRentalPricingDetailsFromArray([]);

        self::assertFalse(isset($details->sellerProductReference));
        self::assertFalse(isset($details->hasRentalTerms));
        self::assertNull($details->currency);
        self::assertNull($details->monthlyRentalAmount);
        self::assertNull($details->monthlyRentalTerms);
        self::assertNull($details->summary);
    }

    public function testCalculatePricingResponseFromArray(): void
    {
        $response = CatalogMapper::calculatePricingResponseFromArray([
            'available_contract_types' => ['rent', 'pay_now'],
            'is_supported' => true,
            'pay_now' => [
                'id' => 'pay-now-1',
                'amount' => ['currency' => 'EUR', 'gross' => 1000, 'net' => 840, 'tax_rate' => 2000],
            ],
            'rent' => [
                [
                    'duration' => 12,
                    'id' => 'rent-12',
                    'monthly_amount' => ['currency' => 'EUR', 'gross' => 100, 'net' => 84, 'tax_rate' => 2000],
                ],
                [
                    'duration' => 24,
                    'id' => 'rent-24',
                    'monthly_amount' => ['currency' => 'EUR', 'gross' => 60, 'net' => 50, 'tax_rate' => 2000],
                ],
            ],
            'summary' => 'Ab 1€/Monat',
        ]);

        self::assertSame(['rent', 'pay_now'], $response->availableContractTypes);
        self::assertTrue($response->isSupported);
        self::assertSame('pay-now-1', $response->payNow->id);
        self::assertSame(1000, $response->payNow->amount->gross);
        self::assertCount(2, $response->rent);
        self::assertSame(12, $response->rent[0]->duration);
        self::assertSame(24, $response->rent[1]->duration);
        self::assertSame('Ab 1€/Monat', $response->summary);
    }

    public function testCalculatePricingResponseFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $response = CatalogMapper::calculatePricingResponseFromArray([]);

        self::assertSame([], $response->availableContractTypes);
        self::assertFalse(isset($response->isSupported));
        self::assertNull($response->payNow);
        self::assertSame([], $response->rent);
        self::assertFalse(isset($response->summary));
    }

    public function testRentContractTermFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $term = CatalogMapper::rentContractTermFromArray([]);

        self::assertFalse(isset($term->duration));
        self::assertNull($term->id);
        self::assertFalse(isset($term->monthlyAmount));
    }

    public function testPayNowContractTermFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $term = CatalogMapper::payNowContractTermFromArray([]);

        self::assertNull($term->id);
        self::assertFalse(isset($term->amount));
    }
}
