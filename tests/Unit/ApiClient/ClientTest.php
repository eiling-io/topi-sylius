<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient;

use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Factory\GuzzleClientFactory;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Order\OrderClient;
use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethodClient;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testCatalogReturnsTheSameInstanceForTheSameCredentials(): void
    {
        $factory = $this->createMock(GuzzleClientFactory::class);
        $factory->expects($this->once())->method('make')->willReturn(new GuzzleClient());

        $client = new Client($factory, new NullLogger());

        $first = $client->catalog();
        $second = $client->catalog();

        self::assertInstanceOf(CatalogClient::class, $first);
        self::assertSame($first, $second);
    }

    public function testCatalogBuildsANewInstancePerCredentialPair(): void
    {
        $factory = $this->createMock(GuzzleClientFactory::class);
        $factory->expects($this->exactly(2))->method('make')->willReturnCallback(
            fn () => new GuzzleClient(),
        );

        $client = new Client($factory, new NullLogger());

        $default = $client->catalog();
        $specific = $client->catalog('client-id', 'client-secret');

        self::assertNotSame($default, $specific);
    }

    public function testOfferReturnsTheSameInstanceForTheSameCredentials(): void
    {
        $factory = $this->createMock(GuzzleClientFactory::class);
        $factory->method('make')->willReturn(new GuzzleClient());

        $client = new Client($factory, new NullLogger());

        self::assertInstanceOf(OfferClient::class, $client->offer());
        self::assertSame($client->offer(), $client->offer());
    }

    public function testShippingMethodReturnsTheSameInstanceForTheSameCredentials(): void
    {
        $factory = $this->createMock(GuzzleClientFactory::class);
        $factory->method('make')->willReturn(new GuzzleClient());

        $client = new Client($factory, new NullLogger());

        self::assertInstanceOf(ShippingMethodClient::class, $client->shippingMethod());
        self::assertSame($client->shippingMethod(), $client->shippingMethod());
    }

    public function testOrderReturnsTheSameInstanceForTheSameCredentials(): void
    {
        $factory = $this->createMock(GuzzleClientFactory::class);
        $factory->method('make')->willReturn(new GuzzleClient());

        $client = new Client($factory, new NullLogger());

        self::assertInstanceOf(OrderClient::class, $client->order());
        self::assertSame($client->order(), $client->order());
    }
}
