<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogProduct;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\MoneyAmountWithOptionalTax;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\PricingRequest;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\RecommendedRentalPricingRequest;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReferenceCollection;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class CatalogClientTest extends TestCase
{
    public function testCheckSupportedMapsTheResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'products' => [
                    [
                        'id' => 'summary-1',
                        'is_supported' => true,
                        'seller_product_reference' => ['source' => 'syliusordernumbers', 'reference' => 'MUG-001'],
                        'available_contract_terms' => ['can_pay_now' => true, 'can_rent' => false, 'rent' => []],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $references = new ProductReferenceCollection();
        $references->add($this->reference('MUG-001'));

        $result = $client->checkSupported($references);

        self::assertCount(1, $result);
        self::assertSame('summary-1', $result[0]->id);
        self::assertTrue($result[0]->isSupported);
    }

    public function testCheckSupportedCachesTheResponseForIdenticalRequests(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode(['products' => []], JSON_THROW_ON_ERROR)),
        ]);

        $references = new ProductReferenceCollection();
        $references->add($this->reference('MUG-001'));

        $first = $client->checkSupported($references);
        // Only one response was queued — a second real HTTP call would throw
        // GuzzleHttp\Exception\OutOfBoundsException, so this only passes if the
        // cache actually short-circuited the second call.
        $second = $client->checkSupported($references);

        self::assertSame($first, $second);
    }

    public function testCheckSupportedBypassesTheCacheWhenToldTo(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode(['products' => []], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['products' => []], JSON_THROW_ON_ERROR)),
        ]);

        $references = new ProductReferenceCollection();
        $references->add($this->reference('MUG-001'));

        $client->checkSupported($references);
        // Would throw GuzzleHttp\Exception\OutOfBoundsException if a second request
        // wasn't actually sent, proving `cache: false` really bypassed the cache.
        $client->checkSupported($references, ['cache' => false]);

        $this->addToAssertionCount(1);
    }

    public function testImportCatalogSendsTheMappedProducts(): void
    {
        $client = $this->clientWithResponses([
            new Response(202),
        ]);

        $product = new CatalogProduct();
        $product->title = 'Topi Mug';
        $product->description = 'A mug.';
        $product->isActive = true;

        $client->importCatalog([$product]);

        $this->addToAssertionCount(1);
    }

    public function testListRecommendedRentalPricesMapsTheResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                [
                    'seller_product_reference' => ['source' => 'syliusordernumbers', 'reference' => 'MUG-001'],
                    'has_rental_terms' => true,
                    'currency' => 'EUR',
                    'monthly_rental_amount' => 116,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $client->listRecommendedRentalPrices([$this->pricingRequest()]);

        self::assertCount(1, $result);
        self::assertTrue($result[0]->hasRentalTerms);
        self::assertSame(116, $result[0]->monthlyRentalAmount);
    }

    public function testListRecommendedRentalPricesLogsAndRethrowsOnAnErrorResponse(): void
    {
        $request = new Request('POST', 'catalog/list-recommended-rental-prices');
        $response = new Response(400, [], 'bad request');
        $exception = new RequestException('Bad request', $request, $response);

        $client = $this->clientWithResponses([$exception]);

        $this->expectException(RequestException::class);

        $client->listRecommendedRentalPrices([$this->pricingRequest()]);
    }

    public function testListRecommendedRentalPricesRethrowsAConnectionErrorWithoutAResponse(): void
    {
        $request = new Request('POST', 'catalog/list-recommended-rental-prices');
        $exception = new RequestException('Connection failed', $request);

        $client = $this->clientWithResponses([$exception]);

        $this->expectException(RequestException::class);

        $client->listRecommendedRentalPrices([$this->pricingRequest()]);
    }

    public function testCalculatePricingMapsTheResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'is_supported' => true,
                'summary' => 'Ab 1€/Monat',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $client->calculatePricing($this->pricingRequestSingle());

        self::assertTrue($response->isSupported);
        self::assertSame('Ab 1€/Monat', $response->summary);
    }

    public function testCalculatePricingRethrowsSilentlyOn404(): void
    {
        $request = new Request('POST', 'catalog/pricing');
        $response = new Response(404, [], 'not found');
        $exception = new RequestException('Not found', $request, $response);

        $client = $this->clientWithResponses([$exception]);

        $this->expectException(RequestException::class);

        $client->calculatePricing($this->pricingRequestSingle());
    }

    public function testCalculatePricingLogsAndRethrowsOnANonNotFoundErrorResponse(): void
    {
        $request = new Request('POST', 'catalog/pricing');
        $response = new Response(500, [], 'server error');
        $exception = new RequestException('Server error', $request, $response);

        $client = $this->clientWithResponses([$exception]);

        $this->expectException(RequestException::class);

        $client->calculatePricing($this->pricingRequestSingle());
    }

    private function reference(string $code): ProductReference
    {
        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = $code;

        return $reference;
    }

    private function pricingRequest(): RecommendedRentalPricingRequest
    {
        $request = new RecommendedRentalPricingRequest();
        $request->sellerProductReference = $this->reference('MUG-001');

        return $request;
    }

    private function pricingRequestSingle(): PricingRequest
    {
        $price = new MoneyAmountWithOptionalTax();
        $price->currency = 'EUR';
        $price->gross = 1000;
        $price->net = 840;

        $request = new PricingRequest();
        $request->price = $price;
        $request->sellerProductReference = $this->reference('MUG-001');

        return $request;
    }

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function clientWithResponses(array $responses): CatalogClient
    {
        $mockHandler = new MockHandler($responses);
        $guzzleClient = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

        return new CatalogClient($guzzleClient, new NullLogger());
    }
}
