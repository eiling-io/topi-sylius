<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Offer;

use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreateOfferData;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class OfferClientTest extends TestCase
{
    public function testCreateOfferMapsTheSuccessfulResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'id' => 'offer-1',
                'status' => 'created',
                'checkout_redirect_url' => 'https://checkout.topi-sandbox.eu/offer-1',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $offer = $client->createOffer($this->offerData());

        self::assertSame('offer-1', $offer->id);
        self::assertSame('created', $offer->status);
        self::assertSame('https://checkout.topi-sandbox.eu/offer-1', $offer->checkoutRedirectUrl);
    }

    public function testValidateOfferMapsTheSuccessfulResponse(): void
    {
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'pricing_overview' => [
                    'instead_of_amount' => ['currency' => 'EUR', 'gross' => 1200, 'net' => 1008],
                    'shipping_amount' => ['currency' => 'EUR', 'gross' => 500, 'net' => 420],
                    'total_amount' => ['currency' => 'EUR', 'gross' => 1700, 'net' => 1428],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $overview = $client->validateOffer($this->offerData());

        self::assertSame(1700, $overview->totalAmount->gross);
    }

    public function testValidateOfferRethrowsAndLogsOnAnErrorResponse(): void
    {
        $request = new Request('POST', 'offers/validate');
        $response = new Response(400, [], json_encode(['message' => 'invalid_length'], JSON_THROW_ON_ERROR));
        $exception = new RequestException('Bad request', $request, $response);

        $client = $this->clientWithResponses([$exception]);

        $this->expectException(RequestException::class);

        $client->validateOffer($this->offerData());
    }

    private function offerData(): CreateOfferData
    {
        $offer = new CreateOfferData();
        $offer->sellerOfferReference = 'order-1';
        $offer->exitRedirect = 'https://shop.example/exit';
        $offer->expiresAt = '2026-01-01T00:00:00+00:00';
        $offer->successRedirect = 'https://shop.example/success';

        return $offer;
    }

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function clientWithResponses(array $responses): OfferClient
    {
        $mockHandler = new MockHandler($responses);
        $guzzleClient = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

        return new OfferClient($guzzleClient, new NullLogger());
    }
}
