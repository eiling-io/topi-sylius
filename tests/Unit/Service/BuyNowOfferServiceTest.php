<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreateOfferData;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreatedOffer;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferClient;
use EilingIo\SyliusTopiPlugin\Service\BuyNowOfferService;
use EilingIo\SyliusTopiPlugin\Service\PendingBuyNowAttemptStore;
use EilingIo\SyliusTopiPlugin\Service\VariantPriceResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * VariantPriceResolver and PendingBuyNowAttemptStore are both `final` (can't be
 * mocked directly), so real instances are used instead — the former backed by a
 * mocked TaxRateResolverInterface with per-variant channel pricing stubs (see also
 * TopiProductExtensionTest), the latter by an in-memory ArrayAdapter (see also
 * PendingBuyNowAttemptStoreTest).
 */
final class BuyNowOfferServiceTest extends TestCase
{
    public function testCreateBuildsAnOfferAndSavesThePendingAttempt(): void
    {
        $variant = $this->variant(code: 'MUG-001', productName: 'Topi Mug', gross: 1000);
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('FASHION_WEB');

        $capturedOffer = null;
        $expectedOffer = new CreatedOffer();
        $expectedOffer->id = 'offer-1';
        $expectedOffer->checkoutRedirectUrl = 'https://checkout.topi-sandbox.eu/offer-1';

        $offerClient = $this->createMock(OfferClient::class);
        $offerClient->expects($this->once())->method('createOffer')->willReturnCallback(
            function (CreateOfferData $offer) use (&$capturedOffer, $expectedOffer) {
                $capturedOffer = $offer;

                return $expectedOffer;
            },
        );

        $client = $this->createMock(Client::class);
        $client->method('offer')->willReturn($offerClient);

        $pendingAttemptStore = new PendingBuyNowAttemptStore(new ArrayAdapter());
        $service = new BuyNowOfferService($client, $this->priceResolver(), $pendingAttemptStore);

        $createdOffer = $service->create(
            [['variant' => $variant, 'quantity' => 2]],
            $channel,
            'de_DE',
            'https://shop.example/success',
            'https://shop.example/exit',
        );

        self::assertSame($expectedOffer, $createdOffer);
        self::assertStringStartsWith('buy-now-', $capturedOffer->sellerOfferReference);
        self::assertCount(1, $capturedOffer->lines);
        self::assertSame('Topi Mug', $capturedOffer->lines[0]->title);
        self::assertSame(2, $capturedOffer->lines[0]->quantity);
        self::assertSame(2000, $capturedOffer->lines[0]->price->gross);
        self::assertSame('MUG-001', $capturedOffer->lines[0]->sellerProductReference->reference);
        self::assertNull($capturedOffer->customer);

        $savedSnapshot = $pendingAttemptStore->get($capturedOffer->sellerOfferReference);
        self::assertSame('FASHION_WEB', $savedSnapshot['channelCode']);
        self::assertSame('de_DE', $savedSnapshot['localeCode']);
        self::assertSame([['variantCode' => 'MUG-001', 'quantity' => 2]], $savedSnapshot['items']);
    }

    public function testCreateSkipsItemsWithoutAResolvablePrice(): void
    {
        $unpriceable = $this->variant(code: 'MUG-002', productName: 'Unpriceable Mug', gross: null);
        $priced = $this->variant(code: 'MUG-001', productName: 'Topi Mug', gross: 1000);

        $capturedOffer = null;
        $offerClient = $this->createMock(OfferClient::class);
        $offerClient->method('createOffer')->willReturnCallback(
            function (CreateOfferData $offer) use (&$capturedOffer) {
                $capturedOffer = $offer;

                return new CreatedOffer();
            },
        );

        $client = $this->createMock(Client::class);
        $client->method('offer')->willReturn($offerClient);

        $pendingAttemptStore = new PendingBuyNowAttemptStore(new ArrayAdapter());
        $service = new BuyNowOfferService($client, $this->priceResolver(), $pendingAttemptStore);

        $service->create(
            [
                ['variant' => $unpriceable, 'quantity' => 1],
                ['variant' => $priced, 'quantity' => 1],
            ],
            $this->createMock(ChannelInterface::class),
            'de_DE',
            'https://shop.example/success',
            'https://shop.example/exit',
        );

        self::assertCount(1, $capturedOffer->lines);
        self::assertSame('MUG-001', $capturedOffer->lines[0]->sellerProductReference->reference);

        $savedSnapshot = $pendingAttemptStore->get($capturedOffer->sellerOfferReference);
        self::assertCount(1, $savedSnapshot['items']);
        self::assertSame('MUG-001', $savedSnapshot['items'][0]['variantCode']);
    }

    public function testCreateThrowsWhenNoItemHasAResolvablePrice(): void
    {
        $unpriceable = $this->variant(code: 'MUG-002', productName: 'Unpriceable Mug', gross: null);

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('offer');

        $service = new BuyNowOfferService(
            $client,
            $this->priceResolver(),
            new PendingBuyNowAttemptStore(new ArrayAdapter()),
        );

        $this->expectException(RuntimeException::class);

        $service->create(
            [['variant' => $unpriceable, 'quantity' => 1]],
            $this->createMock(ChannelInterface::class),
            'de_DE',
            'https://shop.example/success',
            'https://shop.example/exit',
        );
    }

    /**
     * @return ProductVariantInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function variant(string $code, string $productName, ?int $gross): ProductVariantInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn($productName);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn($code);
        $variant->method('getProduct')->willReturn($product);

        if ($gross === null) {
            $variant->method('getChannelPricingForChannel')->willReturn(null);
        } else {
            $channelPricing = $this->createMock(ChannelPricingInterface::class);
            $channelPricing->method('getPrice')->willReturn($gross);
            $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);
        }

        return $variant;
    }

    private function priceResolver(): VariantPriceResolver
    {
        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $taxRateResolver->method('resolve')->willReturn(null);

        return new VariantPriceResolver($taxRateResolver);
    }
}
