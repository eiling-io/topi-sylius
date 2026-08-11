<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use DateInterval;
use DateTime;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CompanyInfo;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreateOfferData;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreatedOffer;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CustomerInfo;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferLinePayload;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\PostalAddress;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\ShippingInfo;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

class OfferService
{
    /**
     * Topi's seller account is EUR-only — offer line/shipping prices are hardcoded
     * to it regardless of the order's own currency, same reasoning (and the same
     * "Currency mismatch" error from Topi's sandbox) as VariantPriceResolver.
     */
    private const CURRENCY = 'EUR';

    public function __construct(private readonly Client $client)
    {
    }

    public function createOffer(
        OrderInterface $order,
        string $successRedirect,
        string $errorRedirect,
    ): CreatedOffer {
        $offer = new CreateOfferData();
        $offer->sellerOfferReference = $order->getNumber();
        $offer->successRedirect = $successRedirect;
        $offer->exitRedirect = $errorRedirect;
        $offer->expiresAt = new DateTime()->add(new DateInterval('P1D'))->format('c');

        foreach ($order->getItems() as $item) {
            if ($item->getUnitPrice() <= 0) {
                continue;
            }

            $line = new OfferLinePayload();
            $line->title = $item->getProductName();
            $line->quantity = $item->getQuantity();

            $price = new MoneyAmount();
            $price->currency = self::CURRENCY;
            $price->gross = $item->getTotal();
            $price->net = $item->getTotal() - $item->getTaxTotal();
            $line->price = $price;

            $productRef = new ProductReference();
            $productRef->source = 'syliusordernumbers';
            $productRef->reference = $item->getVariant()?->getCode() ?? $item->getProductName();
            $line->sellerProductReference = $productRef;

            $offer->lines[] = $line;
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $customer = $order->getCustomer();

        $customerInfo = new CustomerInfo();
        $customerInfo->fullName = $billingAddress->getFullName();
        $customerInfo->email = $customer->getEmail();

        $company = new CompanyInfo();
        $company->name = $this->resolveCompanyName($billingAddress);

        $billing = new PostalAddress();
        $billing->city = $billingAddress->getCity();
        $billing->postalCode = $billingAddress->getPostcode();
        $billing->countryCode = $billingAddress->getCountryCode();
        $billing->line1 = $billingAddress->getStreet();
        $company->billingAddress = $billing;

        $customerInfo->company = $company;
        $offer->customer = $customerInfo;

        $shipping = new PostalAddress();
        $shipping->city = $shippingAddress->getCity();
        $shipping->postalCode = $shippingAddress->getPostcode();
        $shipping->countryCode = $shippingAddress->getCountryCode();
        $shipping->line1 = $shippingAddress->getStreet();
        $offer->shippingAddress = $shipping;

        $shippingInfo = new ShippingInfo();
        $shippingPrice = new MoneyAmount();
        $shippingPrice->currency = self::CURRENCY;
        $shippingPrice->gross = $order->getShippingTotal();
        $shippingPrice->net = $order->getShippingTotal() - $order->getShippingTaxTotal();
        $shippingInfo->price = $shippingPrice;
        $firstShipment = $order->getShipments()->first();
        $shippingInfo->sellerShippingReference = $firstShipment ? $firstShipment->getMethod()->getCode() : 'default';
        $offer->shipping = $shippingInfo;

        return $this->client->offer()->createOffer($offer);
    }

    /**
     * Topi requires `company.name` to be at least 3 characters (their offer is
     * always a B2B financing product) — Sylius' checkout address form leaves
     * "Company" optional, so a private customer (guest or logged in, doesn't
     * matter) with nothing in that field would otherwise send an empty string and
     * get a hard "invalid_length" rejection from Topi's API, right when the order
     * is placed. Falling back to the billing name isn't a real company name, but
     * Topi has no "no company" option to fall back to instead, and this at least
     * keeps the offer creatable for private customers.
     */
    private function resolveCompanyName(AddressInterface $billingAddress): string
    {
        $company = trim((string) $billingAddress->getCompany());

        return $company !== '' ? $company : (string) $billingAddress->getFullName();
    }
}
