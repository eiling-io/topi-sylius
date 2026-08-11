<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Order;

use EilingIo\SyliusTopiPlugin\ApiClient\Order\OrderMapper;
use PHPUnit\Framework\TestCase;

final class OrderMapperTest extends TestCase
{
    public function testOrderFromArrayWithAllFields(): void
    {
        $order = OrderMapper::orderFromArray([
            'id' => 'order-1',
            'offer_id' => 'offer-1',
            'seller_offer_reference' => 'buy-now-abc',
            'status' => 'confirmed',
            'assets' => [['type' => 'image', 'url' => 'https://example.com/a.png']],
            'metadata' => ['foo' => 'bar'],
            'customer' => [
                'email' => 'jane@example.com',
                'company' => [
                    'name' => 'Acme GmbH',
                    'billing_address' => [
                        'city' => 'Berlin',
                        'country_code' => 'DE',
                        'line1' => 'Musterstraße 1',
                        'postal_code' => '10115',
                    ],
                ],
            ],
            'shipping_address' => [
                'city' => 'Berlin',
                'country_code' => 'DE',
                'line1' => 'Musterstraße 1',
                'postal_code' => '10115',
            ],
        ]);

        self::assertSame('order-1', $order->id);
        self::assertSame('offer-1', $order->offerId);
        self::assertSame('buy-now-abc', $order->sellerOfferReference);
        self::assertSame('confirmed', $order->status);
        self::assertSame([['type' => 'image', 'url' => 'https://example.com/a.png']], $order->assets);
        self::assertSame(['foo' => 'bar'], $order->metadata);
        self::assertSame('jane@example.com', $order->customer->email);
        self::assertSame('Acme GmbH', $order->customer->company->name);
        self::assertSame('Berlin', $order->shippingAddress->city);
    }

    public function testOrderFromArrayLeavesFieldsUnsetWhenMissing(): void
    {
        $order = OrderMapper::orderFromArray([]);

        self::assertFalse(isset($order->id));
        self::assertFalse(isset($order->offerId));
        self::assertFalse(isset($order->sellerOfferReference));
        self::assertFalse(isset($order->status));
        self::assertFalse(isset($order->assets));
        self::assertNull($order->metadata);
        self::assertNull($order->customer);
        self::assertNull($order->shippingAddress);
    }
}
