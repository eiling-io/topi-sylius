<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\ShippingMethod;

use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethod;
use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethodClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class ShippingMethodClientTest extends TestCase
{
    public function testListYieldsASinglePageWithoutFollowingPagination(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'data' => [
                    ['seller_shipping_method_reference' => 'ups', 'supported' => true],
                ],
                'pagination' => ['has_more' => false, 'page' => 0],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $items = [...$client->list()];

        self::assertSame([['seller_shipping_method_reference' => 'ups', 'supported' => true]], $items);
    }

    public function testListFollowsPaginationUntilHasMoreIsFalse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'data' => [['seller_shipping_method_reference' => 'ups', 'supported' => true]],
                'pagination' => ['has_more' => true, 'page' => 0],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'data' => [['seller_shipping_method_reference' => 'dhl_express', 'supported' => true]],
                'pagination' => ['has_more' => false, 'page' => 1],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $items = [...$client->list()];

        self::assertCount(2, $items);
        self::assertSame('ups', $items[0]['seller_shipping_method_reference']);
        self::assertSame('dhl_express', $items[1]['seller_shipping_method_reference']);
    }

    public function testCreateSendsTheMappedShippingMethod(): void
    {
        $client = $this->clientWithResponses([
            new Response(201),
        ]);

        $client->create($this->shippingMethod());

        $this->addToAssertionCount(1);
    }

    public function testCreateSilentlyIgnoresAnAlreadyExistsConflict(): void
    {
        $request = new Request('POST', 'shipping-method/method');
        $response = new Response(422, [], 'already exists');
        $exception = new ClientException('Unprocessable', $request, $response);

        $client = $this->clientWithResponses([$exception]);

        $client->create($this->shippingMethod());

        $this->addToAssertionCount(1);
    }

    public function testCreateRethrowsAnyOtherClientError(): void
    {
        $request = new Request('POST', 'shipping-method/method');
        $response = new Response(400, [], 'bad request');
        $exception = new ClientException('Bad request', $request, $response);

        $client = $this->clientWithResponses([$exception]);

        $this->expectException(ClientException::class);

        $client->create($this->shippingMethod());
    }

    private function shippingMethod(): ShippingMethod
    {
        $method = new ShippingMethod();
        $method->name = 'UPS';
        $method->sellerShippingMethodReference = 'ups';

        return $method;
    }

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function clientWithResponses(array $responses): ShippingMethodClient
    {
        $mockHandler = new MockHandler($responses);
        $guzzleClient = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

        return new ShippingMethodClient($guzzleClient, new NullLogger());
    }
}
