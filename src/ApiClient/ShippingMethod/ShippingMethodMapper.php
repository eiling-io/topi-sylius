<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod;

class ShippingMethodMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ShippingMethod $shippingMethod): array
    {
        return [
            'name' => $shippingMethod->name,
            'seller_shipping_method_reference' => $shippingMethod->sellerShippingMethodReference,
        ];
    }
}
