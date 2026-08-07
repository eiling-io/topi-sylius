<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use DateTime;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\CommonMapper;

use function array_map;

class OfferMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function createOfferToArray(CreateOfferData $offer): array
    {
        $data = [
            'sales_channel' => $offer->salesChannel,
            'seller_offer_reference' => $offer->sellerOfferReference,
            'exit_redirect' => $offer->exitRedirect,
            'expires_at' => $offer->expiresAt,
            'success_redirect' => $offer->successRedirect,
        ];

        // Omitted (not merely null-valued) for "buy now" offers, so Topi's hosted
        // checkout knows to collect them itself instead of treating an empty object
        // as "the seller says this customer has no address".
        if ($offer->customer !== null) {
            $data['customer'] = self::customerInfoToArray($offer->customer);
        }
        if ($offer->shipping !== null) {
            $data['shipping'] = self::shippingInfoToArray($offer->shipping);
        }
        if ($offer->shippingAddress !== null) {
            $data['shipping_address'] = self::postalAddressToArray($offer->shippingAddress);
        }
        if ($offer->lines !== []) {
            $data['lines'] = array_map(self::offerLineToArray(...), $offer->lines);
        }
        if ($offer->metadata !== null) {
            $data['metadata'] = $offer->metadata;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerInfoToArray(CustomerInfo $customer): array
    {
        $data = [
            'company' => self::companyInfoToArray($customer->company),
            'email' => $customer->email,
        ];

        if ($customer->customerGroup !== null) {
            $data['customer_group'] = $customer->customerGroup;
        }
        if ($customer->fullName !== null) {
            $data['full_name'] = $customer->fullName;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function companyInfoToArray(CompanyInfo $company): array
    {
        $data = [
            'billing_address' => self::postalAddressToArray($company->billingAddress),
            'name' => $company->name,
        ];

        if ($company->taxNumber !== null) {
            $data['tax_number'] = $company->taxNumber;
        }
        if ($company->vatNumber !== null) {
            $data['vat_number'] = $company->vatNumber;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function postalAddressToArray(PostalAddress $address): array
    {
        $data = [
            'city' => $address->city,
            'country_code' => $address->countryCode,
            'line1' => $address->line1,
            'postal_code' => $address->postalCode,
        ];

        if ($address->line2 !== null) {
            $data['line2'] = $address->line2;
        }
        if ($address->region !== null) {
            $data['region'] = $address->region;
        }
        if ($address->recipientName !== null) {
            $data['recipient_name'] = $address->recipientName;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function offerLineToArray(OfferLinePayload $line): array
    {
        $data = [
            'price' => CommonMapper::moneyAmountToArray($line->price),
            'quantity' => $line->quantity,
            'seller_product_reference' => CommonMapper::productReferenceToArray($line->sellerProductReference),
            'title' => $line->title,
        ];

        if ($line->subtitle !== null) {
            $data['subtitle'] = $line->subtitle;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function shippingInfoToArray(ShippingInfo $shipping): array
    {
        return [
            'price' => CommonMapper::moneyAmountToArray($shipping->price),
            'seller_shipping_reference' => $shipping->sellerShippingReference,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function customerInfoFromArray(array $data): CustomerInfo
    {
        $customer = new CustomerInfo();

        if (isset($data['company'])) {
            $customer->company = self::companyInfoFromArray($data['company']);
        }
        if (isset($data['customer_group'])) {
            $customer->customerGroup = $data['customer_group'];
        }
        if (isset($data['email'])) {
            $customer->email = $data['email'];
        }
        if (isset($data['full_name'])) {
            $customer->fullName = $data['full_name'];
        }

        return $customer;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function companyInfoFromArray(array $data): CompanyInfo
    {
        $company = new CompanyInfo();

        if (isset($data['billing_address'])) {
            $company->billingAddress = self::postalAddressFromArray($data['billing_address']);
        }
        if (isset($data['name'])) {
            $company->name = $data['name'];
        }
        if (isset($data['tax_number'])) {
            $company->taxNumber = $data['tax_number'];
        }
        if (isset($data['vat_number'])) {
            $company->vatNumber = $data['vat_number'];
        }

        return $company;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function postalAddressFromArray(array $data): PostalAddress
    {
        $address = new PostalAddress();

        if (isset($data['city'])) {
            $address->city = $data['city'];
        }
        if (isset($data['country_code'])) {
            $address->countryCode = $data['country_code'];
        }
        if (isset($data['line1'])) {
            $address->line1 = $data['line1'];
        }
        if (isset($data['line2'])) {
            $address->line2 = $data['line2'];
        }
        if (isset($data['postal_code'])) {
            $address->postalCode = $data['postal_code'];
        }
        if (isset($data['region'])) {
            $address->region = $data['region'];
        }
        if (isset($data['recipient_name'])) {
            $address->recipientName = $data['recipient_name'];
        }

        return $address;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createdOfferFromArray(array $data): CreatedOffer
    {
        $offer = new CreatedOffer();

        if (isset($data['id'])) {
            $offer->id = $data['id'];
        }
        if (isset($data['status'])) {
            $offer->status = $data['status'];
        }
        if (isset($data['checkout_redirect_url'])) {
            $offer->checkoutRedirectUrl = $data['checkout_redirect_url'];
        }
        if (isset($data['seller_offer_reference'])) {
            $offer->sellerOfferReference = $data['seller_offer_reference'];
        }
        if (isset($data['created_at'])) {
            $offer->createdAt = new DateTime($data['created_at']);
        }

        return $offer;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function pricingOverviewFromArray(array $data): PricingOverview
    {
        $overview = new PricingOverview();

        if (isset($data['breakdown'])) {
            $overview->breakdown = array_map(self::breakdownLineFromArray(...), $data['breakdown']);
        }
        if (isset($data['instead_of_amount'])) {
            $overview->insteadOfAmount = CommonMapper::moneyAmountFromArray($data['instead_of_amount']);
        }
        if (isset($data['shipping_amount'])) {
            $overview->shippingAmount = CommonMapper::moneyAmountFromArray($data['shipping_amount']);
        }
        if (isset($data['total_amount'])) {
            $overview->totalAmount = CommonMapper::moneyAmountFromArray($data['total_amount']);
        }

        return $overview;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function breakdownLineFromArray(array $data): BreakdownLine
    {
        $line = new BreakdownLine();

        if (isset($data['amount'])) {
            $line->amount = CommonMapper::moneyAmountFromArray($data['amount']);
        }
        if (isset($data['title'])) {
            $line->title = $data['title'];
        }
        if (isset($data['tooltip'])) {
            $line->tooltip = $data['tooltip'];
        }

        return $line;
    }
}
