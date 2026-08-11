<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Twig;

use EilingIo\SyliusTopiPlugin\MinOrderValue;
use EilingIo\SyliusTopiPlugin\Service\VariantPriceResolver;
use EilingIo\SyliusTopiPlugin\Twig\TopiProductExtension;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

/**
 * VariantPriceResolver is `final` (can't be mocked directly) — a real instance
 * backed by a mocked TaxRateResolverInterface and a variant stubbed with a channel
 * price is used instead, keeping this a unit test of TopiProductExtension's own
 * logic without pulling in a Symfony/Doctrine test setup for VariantPriceResolver.
 */
final class TopiProductExtensionTest extends TestCase
{
    public function testIsTopiProductIsFalseWhenTheFeatureIsDisabled(): void
    {
        $extension = $this->extension(topiEnabled: false);
        $variant = $this->createMock(ProductVariantInterface::class);

        self::assertFalse($extension->isTopiProduct($variant));
    }

    public function testIsTopiProductIsFalseForADisabledVariant(): void
    {
        $extension = $this->extension(topiEnabled: true);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isEnabled')->willReturn(false);

        self::assertFalse($extension->isTopiProduct($variant));
    }

    public function testIsTopiProductIsFalseBelowTheMinimumOrderValue(): void
    {
        $extension = $this->extension(topiEnabled: true);
        $variant = $this->pricedVariant(MinOrderValue::CENTS - 1);
        $variant->method('isEnabled')->willReturn(true);

        self::assertFalse($extension->isTopiProduct($variant));
    }

    public function testIsTopiProductIsFalseWhenTheVariantHasNoResolvablePrice(): void
    {
        $extension = $this->extension(topiEnabled: true);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isEnabled')->willReturn(true);
        $variant->method('getChannelPricingForChannel')->willReturn(null);

        self::assertFalse($extension->isTopiProduct($variant));
    }

    public function testIsTopiProductIsTrueAtOrAboveTheMinimumOrderValue(): void
    {
        $extension = $this->extension(topiEnabled: true);
        $variant = $this->pricedVariant(MinOrderValue::CENTS);
        $variant->method('isEnabled')->willReturn(true);

        self::assertTrue($extension->isTopiProduct($variant));
    }

    public function testGetPdpItemDefaultsQuantityToOne(): void
    {
        $extension = $this->extension(topiEnabled: true);
        $variant = $this->pricedVariant(4068);
        $variant->method('getCode')->willReturn('MUG-001');

        $item = $extension->getPdpItem($variant);

        self::assertSame(1, $item['quantity']);
        self::assertSame(4068, $item['price']['gross']);
        self::assertSame('MUG-001', $item['sellerProductReference']['reference']);
        self::assertSame('syliusordernumbers', $item['sellerProductReference']['source']);
    }

    public function testGetPdpItemIncludesTaxRateWhenResolved(): void
    {
        $extension = $this->extension(topiEnabled: true, taxRateAmount: 0.2);
        $variant = $this->pricedVariant(4068);
        $variant->method('getCode')->willReturn('MUG-001');

        $item = $extension->getPdpItem($variant, 3);

        self::assertSame(3, $item['quantity']);
        self::assertSame(2000, $item['price']['taxRate']);
    }

    public function testGetPdpItemThrowsWhenThereIsNoResolvablePrice(): void
    {
        $extension = $this->extension(topiEnabled: true);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getChannelPricingForChannel')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        $extension->getPdpItem($variant);
    }

    public function testGetGlobalsExposesTheWidgetConfiguration(): void
    {
        $extension = $this->extension(topiEnabled: true, widgetId: 'widget-123', enableLive: true);

        self::assertSame([
            'topi_enabled' => true,
            'topi_widget_id' => 'widget-123',
            'topi_enable_live' => true,
        ], $extension->getGlobals());
    }

    public function testGetGlobalsIsDisabledWithoutAWidgetIdEvenIfTheFeatureFlagIsOn(): void
    {
        $extension = $this->extension(topiEnabled: true, widgetId: '');

        self::assertFalse($extension->getGlobals()['topi_enabled']);
    }

    /**
     * @return ProductVariantInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function pricedVariant(int $gross): ProductVariantInterface
    {
        $channelPricing = $this->createMock(ChannelPricingInterface::class);
        $channelPricing->method('getPrice')->willReturn($gross);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);

        return $variant;
    }

    private function extension(
        bool $topiEnabled,
        ?float $taxRateAmount = null,
        string $widgetId = 'widget-123',
        bool $enableLive = false,
    ): TopiProductExtension {
        $channel = $this->createMock(ChannelInterface::class);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        if ($taxRateAmount === null) {
            $taxRateResolver->method('resolve')->willReturn(null);
        } else {
            $taxRate = $this->createMock(\Sylius\Component\Taxation\Model\TaxRateInterface::class);
            $taxRate->method('getAmount')->willReturn($taxRateAmount);
            $taxRateResolver->method('resolve')->willReturn($taxRate);
        }

        $priceResolver = new VariantPriceResolver($taxRateResolver);

        return new TopiProductExtension($channelContext, $priceResolver, $topiEnabled, $widgetId, $enableLive);
    }
}
