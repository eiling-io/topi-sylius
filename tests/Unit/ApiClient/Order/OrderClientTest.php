<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Order;

use EilingIo\SyliusTopiPlugin\ApiClient\Order\OrderClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Order\SetOrderMetadataData;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class OrderClientTest extends TestCase
{
    public function testGetOrderMapsTheResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'id' => 'order-1',
                'offer_id' => 'offer-1',
                'seller_offer_reference' => 'buy-now-abc',
                'status' => 'confirmed',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $order = $client->getOrder('order-1');

        self::assertSame('order-1', $order->id);
        self::assertSame('confirmed', $order->status);
    }

    public function testSetOrderMetadataMapsTheResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'id' => 'order-1',
                'offer_id' => 'offer-1',
                'seller_offer_reference' => 'buy-now-abc',
                'status' => 'confirmed',
                'metadata' => ['synced' => 'true'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $data = new SetOrderMetadataData();
        $data->orderId = 'order-1';
        $data->metadata = ['synced' => 'true'];

        $order = $client->setOrderMetadata($data);

        self::assertSame(['synced' => 'true'], $order->metadata);
    }

    /**
     * @param array<int, Response> $responses
     */
    private function clientWithResponses(array $responses): OrderClient
    {
        $mockHandler = new MockHandler($responses);
        $guzzleClient = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

        return new OrderClient($guzzleClient, new NullLogger());
    }
}
