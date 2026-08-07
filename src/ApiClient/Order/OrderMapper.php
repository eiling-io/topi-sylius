<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Order;

use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferMapper;

class OrderMapper
{
    /**
     * @param array<string, mixed> $data
     */
    public static function orderFromArray(array $data): Order
    {
        $order = new Order();

        if (isset($data['id'])) {
            $order->id = $data['id'];
        }
        if (isset($data['offer_id'])) {
            $order->offerId = $data['offer_id'];
        }
        if (isset($data['seller_offer_reference'])) {
            $order->sellerOfferReference = $data['seller_offer_reference'];
        }
        if (isset($data['status'])) {
            $order->status = $data['status'];
        }
        if (isset($data['assets'])) {
            $order->assets = $data['assets'];
        }
        if (isset($data['metadata'])) {
            $order->metadata = $data['metadata'];
        }
        if (isset($data['customer'])) {
            $order->customer = OfferMapper::customerInfoFromArray($data['customer']);
        }
        if (isset($data['shipping_address'])) {
            $order->shippingAddress = OfferMapper::postalAddressFromArray($data['shipping_address']);
        }

        return $order;
    }
}
