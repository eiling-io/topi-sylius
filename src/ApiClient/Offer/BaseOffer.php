<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

class BaseOffer
{
    /**
     * Nullable because "buy now" offers (created before any Sylius checkout has run —
     * see BuyNowOfferService) omit customer/shipping/shippingAddress entirely: Topi's
     * hosted checkout collects them itself, and we get them back via the order.created
     * webhook / GET /orders/{id} instead.
     */
    public ?CustomerInfo $customer = null;

    /**
     * @var OfferLinePayload[]
     */
    public array $lines = [];

    /**
     * @var array<string, string>|null
     */
    public ?array $metadata = null;

    public string $salesChannel = 'ecommerce';

    public string $sellerOfferReference;

    public ?ShippingInfo $shipping = null;

    public ?PostalAddress $shippingAddress = null;
}
