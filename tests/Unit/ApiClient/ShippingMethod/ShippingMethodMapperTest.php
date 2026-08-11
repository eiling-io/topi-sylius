<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\ShippingMethod;

use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethod;
use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethodMapper;
use PHPUnit\Framework\TestCase;

final class ShippingMethodMapperTest extends TestCase
{
    public function testToArray(): void
    {
        $shippingMethod = new ShippingMethod();
        $shippingMethod->name = 'UPS';
        $shippingMethod->sellerShippingMethodReference = 'ups';

        self::assertSame([
            'name' => 'UPS',
            'seller_shipping_method_reference' => 'ups',
        ], ShippingMethodMapper::toArray($shippingMethod));
    }
}
