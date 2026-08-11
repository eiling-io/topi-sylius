<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Offer;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CompanyInfo;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreateOfferData;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CustomerInfo;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferLinePayload;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferMapper;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\PostalAddress;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\ShippingInfo;
use PHPUnit\Framework\TestCase;

final class OfferMapperTest extends TestCase
{
    public function testCreateOfferToArrayWithOnlyRequiredFields(): void
    {
        $offer = new CreateOfferData();
        $offer->sellerOfferReference = 'order-1';
        $offer->exitRedirect = 'https://shop.example/exit';
        $offer->expiresAt = '2026-01-01T00:00:00+00:00';
        $offer->successRedirect = 'https://shop.example/success';

        $data = OfferMapper::createOfferToArray($offer);

        self::assertSame('ecommerce', $data['sales_channel']);
        self::assertSame('order-1', $data['seller_offer_reference']);
        self::assertSame('https://shop.example/exit', $data['exit_redirect']);
        self::assertSame('2026-01-01T00:00:00+00:00', $data['expires_at']);
        self::assertSame('https://shop.example/success', $data['success_redirect']);
        self::assertArrayNotHasKey('customer', $data);
        self::assertArrayNotHasKey('shipping', $data);
        self::assertArrayNotHasKey('shipping_address', $data);
        self::assertArrayNotHasKey('lines', $data);
        self::assertArrayNotHasKey('metadata', $data);
    }

    public function testCreateOfferToArrayWithAllOptionalFields(): void
    {
        $offer = new CreateOfferData();
        $offer->sellerOfferReference = 'order-1';
        $offer->exitRedirect = 'https://shop.example/exit';
        $offer->expiresAt = '2026-01-01T00:00:00+00:00';
        $offer->successRedirect = 'https://shop.example/success';
        $offer->metadata = ['foo' => 'bar'];

        $customer = new CustomerInfo();
        $customer->email = 'jane@example.com';
        $customer->fullName = 'Jane Guest';
        $customer->customerGroup = 'retail';
        $company = new CompanyInfo();
        $company->name = 'Acme GmbH';
        $company->taxNumber = 'TAX-1';
        $company->vatNumber = 'VAT-1';
        $company->billingAddress = $this->address();
        $customer->company = $company;
        $offer->customer = $customer;

        $shippingPrice = new MoneyAmount();
        $shippingPrice->currency = 'EUR';
        $shippingPrice->gross = 500;
        $shippingPrice->net = 420;
        $shipping = new ShippingInfo();
        $shipping->price = $shippingPrice;
        $shipping->sellerShippingReference = 'ups';
        $offer->shipping = $shipping;

        $offer->shippingAddress = $this->address(recipientName: 'Jane Guest');

        $linePrice = new MoneyAmount();
        $linePrice->currency = 'EUR';
        $linePrice->gross = 1000;
        $linePrice->net = 840;
        $line = new OfferLinePayload();
        $line->title = 'Topi Mug';
        $line->subtitle = '1x';
        $line->quantity = 2;
        $line->price = $linePrice;
        $productRef = new ProductReference();
        $productRef->source = 'syliusordernumbers';
        $productRef->reference = 'MUG-001';
        $line->sellerProductReference = $productRef;
        $offer->lines = [$line];

        $data = OfferMapper::createOfferToArray($offer);

        self::assertSame('jane@example.com', $data['customer']['email']);
        self::assertSame('Jane Guest', $data['customer']['full_name']);
        self::assertSame('retail', $data['customer']['customer_group']);
        self::assertSame('Acme GmbH', $data['customer']['company']['name']);
        self::assertSame('TAX-1', $data['customer']['company']['tax_number']);
        self::assertSame('VAT-1', $data['customer']['company']['vat_number']);
        self::assertSame(500, $data['shipping']['price']['gross']);
        self::assertSame('ups', $data['shipping']['seller_shipping_reference']);
        self::assertSame('Jane Guest', $data['shipping_address']['recipient_name']);
        self::assertCount(1, $data['lines']);
        self::assertSame('Topi Mug', $data['lines'][0]['title']);
        self::assertSame('1x', $data['lines'][0]['subtitle']);
        self::assertSame(2, $data['lines'][0]['quantity']);
        self::assertSame(['foo' => 'bar'], $data['metadata']);
    }

    public function testCustomerInfoToArrayWithoutOptionalFields(): void
    {
        $customer = new CustomerInfo();
        $customer->email = 'jane@example.com';
        $customer->company = new CompanyInfo();
        $customer->company->name = 'Jane Guest';
        $customer->company->billingAddress = $this->address();

        $data = OfferMapper::customerInfoToArray($customer);

        self::assertSame('jane@example.com', $data['email']);
        self::assertArrayNotHasKey('customer_group', $data);
        self::assertArrayNotHasKey('full_name', $data);
    }

    public function testPostalAddressToArrayWithOnlyRequiredFields(): void
    {
        $address = $this->address();

        $data = OfferMapper::postalAddressToArray($address);

        self::assertSame('Berlin', $data['city']);
        self::assertSame('DE', $data['country_code']);
        self::assertSame('Musterstraße 1', $data['line1']);
        self::assertSame('10115', $data['postal_code']);
        self::assertArrayNotHasKey('line2', $data);
        self::assertArrayNotHasKey('region', $data);
        self::assertArrayNotHasKey('recipient_name', $data);
    }

    public function testPostalAddressToArrayWithOptionalFields(): void
    {
        $address = $this->address();
        $address->line2 = 'Hinterhof';
        $address->region = 'Berlin';
        $address->recipientName = 'Jane Guest';

        $data = OfferMapper::postalAddressToArray($address);

        self::assertSame('Hinterhof', $data['line2']);
        self::assertSame('Berlin', $data['region']);
        self::assertSame('Jane Guest', $data['recipient_name']);
    }

    public function testOfferLineToArrayWithoutSubtitle(): void
    {
        $price = new MoneyAmount();
        $price->currency = 'EUR';
        $price->gross = 1000;
        $price->net = 840;

        $productRef = new ProductReference();
        $productRef->source = 'syliusordernumbers';
        $productRef->reference = 'MUG-001';

        $line = new OfferLinePayload();
        $line->title = 'Topi Mug';
        $line->quantity = 1;
        $line->price = $price;
        $line->sellerProductReference = $productRef;

        $data = OfferMapper::offerLineToArray($line);

        self::assertArrayNotHasKey('subtitle', $data);
        self::assertSame('Topi Mug', $data['title']);
        self::assertSame(1, $data['quantity']);
    }

    public function testCustomerInfoFromArrayWithAllFields(): void
    {
        $customer = OfferMapper::customerInfoFromArray([
            'company' => [
                'name' => 'Acme GmbH',
                'billing_address' => ['city' => 'Berlin', 'country_code' => 'DE', 'line1' => 'x', 'postal_code' => '1'],
            ],
            'customer_group' => 'retail',
            'email' => 'jane@example.com',
            'full_name' => 'Jane Guest',
        ]);

        self::assertSame('Acme GmbH', $customer->company->name);
        self::assertSame('retail', $customer->customerGroup);
        self::assertSame('jane@example.com', $customer->email);
        self::assertSame('Jane Guest', $customer->fullName);
    }

    public function testCustomerInfoFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $customer = OfferMapper::customerInfoFromArray([]);

        self::assertFalse(isset($customer->company));
        self::assertNull($customer->customerGroup);
        self::assertFalse(isset($customer->email));
        self::assertNull($customer->fullName);
    }

    public function testCompanyInfoFromArrayWithAllFields(): void
    {
        $company = OfferMapper::companyInfoFromArray([
            'billing_address' => ['city' => 'Berlin', 'country_code' => 'DE', 'line1' => 'x', 'postal_code' => '1'],
            'name' => 'Acme GmbH',
            'tax_number' => 'TAX-1',
            'vat_number' => 'VAT-1',
        ]);

        self::assertSame('Berlin', $company->billingAddress->city);
        self::assertSame('Acme GmbH', $company->name);
        self::assertSame('TAX-1', $company->taxNumber);
        self::assertSame('VAT-1', $company->vatNumber);
    }

    public function testCompanyInfoFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $company = OfferMapper::companyInfoFromArray([]);

        self::assertFalse(isset($company->billingAddress));
        self::assertFalse(isset($company->name));
        self::assertNull($company->taxNumber);
        self::assertNull($company->vatNumber);
    }

    public function testPostalAddressFromArrayWithAllFields(): void
    {
        $address = OfferMapper::postalAddressFromArray([
            'city' => 'Berlin',
            'country_code' => 'DE',
            'line1' => 'Musterstraße 1',
            'line2' => 'Hinterhof',
            'postal_code' => '10115',
            'region' => 'Berlin',
            'recipient_name' => 'Jane Guest',
        ]);

        self::assertSame('Berlin', $address->city);
        self::assertSame('DE', $address->countryCode);
        self::assertSame('Musterstraße 1', $address->line1);
        self::assertSame('Hinterhof', $address->line2);
        self::assertSame('10115', $address->postalCode);
        self::assertSame('Berlin', $address->region);
        self::assertSame('Jane Guest', $address->recipientName);
    }

    public function testPostalAddressFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $address = OfferMapper::postalAddressFromArray([]);

        self::assertFalse(isset($address->city));
        self::assertFalse(isset($address->countryCode));
        self::assertFalse(isset($address->line1));
        self::assertNull($address->line2);
        self::assertFalse(isset($address->postalCode));
        self::assertNull($address->region);
        self::assertNull($address->recipientName);
    }

    public function testCreatedOfferFromArrayWithAllFields(): void
    {
        $offer = OfferMapper::createdOfferFromArray([
            'id' => 'offer-1',
            'status' => 'created',
            'checkout_redirect_url' => 'https://checkout.topi-sandbox.eu/offer-1',
            'seller_offer_reference' => 'order-1',
            'created_at' => '2026-01-01T00:00:00+00:00',
        ]);

        self::assertSame('offer-1', $offer->id);
        self::assertSame('created', $offer->status);
        self::assertSame('https://checkout.topi-sandbox.eu/offer-1', $offer->checkoutRedirectUrl);
        self::assertSame('order-1', $offer->sellerOfferReference);
        self::assertSame('2026-01-01', $offer->createdAt->format('Y-m-d'));
    }

    public function testCreatedOfferFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $offer = OfferMapper::createdOfferFromArray([]);

        self::assertFalse(isset($offer->id));
        self::assertFalse(isset($offer->status));
        self::assertSame('', $offer->checkoutRedirectUrl);
        self::assertFalse(isset($offer->sellerOfferReference));
        self::assertFalse(isset($offer->createdAt));
    }

    public function testPricingOverviewFromArray(): void
    {
        $overview = OfferMapper::pricingOverviewFromArray([
            'breakdown' => [
                [
                    'amount' => ['currency' => 'EUR', 'gross' => 100, 'net' => 84],
                    'title' => 'Monat 1',
                    'tooltip' => 'inkl. MwSt.',
                ],
                [
                    'amount' => ['currency' => 'EUR', 'gross' => 100, 'net' => 84],
                    'title' => 'Monat 2',
                ],
            ],
            'instead_of_amount' => ['currency' => 'EUR', 'gross' => 1200, 'net' => 1008],
            'shipping_amount' => ['currency' => 'EUR', 'gross' => 500, 'net' => 420],
            'total_amount' => ['currency' => 'EUR', 'gross' => 1700, 'net' => 1428],
        ]);

        self::assertCount(2, $overview->breakdown);
        self::assertSame('Monat 1', $overview->breakdown[0]->title);
        self::assertSame('inkl. MwSt.', $overview->breakdown[0]->tooltip);
        self::assertNull($overview->breakdown[1]->tooltip);
        self::assertSame(1200, $overview->insteadOfAmount->gross);
        self::assertSame(500, $overview->shippingAmount->gross);
        self::assertSame(1700, $overview->totalAmount->gross);
    }

    public function testPricingOverviewFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $overview = OfferMapper::pricingOverviewFromArray([]);

        self::assertSame([], $overview->breakdown);
        self::assertFalse(isset($overview->insteadOfAmount));
        self::assertFalse(isset($overview->shippingAmount));
        self::assertFalse(isset($overview->totalAmount));
    }

    private function address(?string $recipientName = null): PostalAddress
    {
        $address = new PostalAddress();
        $address->city = 'Berlin';
        $address->countryCode = 'DE';
        $address->line1 = 'Musterstraße 1';
        $address->postalCode = '10115';
        $address->recipientName = $recipientName;

        return $address;
    }
}
