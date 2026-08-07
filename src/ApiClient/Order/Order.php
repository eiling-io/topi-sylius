<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Order;

use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CustomerInfo;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\PostalAddress;

class Order
{
    public string $id;

    public string $offerId;

    public string $sellerOfferReference;

    /**
     * @var 'created'|'confirmed'|'acknowledged'|'accepted'|'partially_fulfilled'|'completed'|'canceled'|'rejected' $status
     */
    public string $status;

    /**
     * @var array<int, mixed>
     */
    public array $assets;

    /**
     * @var array<string, string>|null
     */
    public ?array $metadata = null;

    /**
     * Only populated for "buy now" orders (see BuyNowOrderCreator) — the checkout-time
     * flow already has this from the Sylius order itself and never reads it back.
     */
    public ?CustomerInfo $customer = null;

    public ?PostalAddress $shippingAddress = null;
}
