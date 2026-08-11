<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use EilingIo\SyliusTopiPlugin\Service\VariantPriceResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

final class VariantPriceResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenTheVariantHasNoChannelPrice(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getChannelPricingForChannel')->willReturn(null);

        $channel = $this->createMock(ChannelInterface::class);
        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);

        $resolver = new VariantPriceResolver($taxRateResolver);

        self::assertNull($resolver->resolve($variant, $channel));
    }

    public function testResolveComputesNetFromTheTaxRateWhenOneApplies(): void
    {
        $channelPricing = $this->createMock(ChannelPricingInterface::class);
        $channelPricing->method('getPrice')->willReturn(1000);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);

        $channel = $this->createMock(ChannelInterface::class);

        $taxRate = $this->createMock(TaxRateInterface::class);
        $taxRate->method('getAmount')->willReturn(0.2);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $taxRateResolver->method('resolve')->willReturn($taxRate);

        $resolver = new VariantPriceResolver($taxRateResolver);
        $price = $resolver->resolve($variant, $channel);

        self::assertNotNull($price);
        self::assertSame('EUR', $price->currency);
        self::assertSame(1000, $price->gross);
        self::assertSame(833, $price->net);
        self::assertSame(2000, $price->taxRate);
    }

    public function testResolveUsesGrossAsNetWhenNoTaxRateApplies(): void
    {
        $channelPricing = $this->createMock(ChannelPricingInterface::class);
        $channelPricing->method('getPrice')->willReturn(1000);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);

        $channel = $this->createMock(ChannelInterface::class);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $taxRateResolver->method('resolve')->willReturn(null);

        $resolver = new VariantPriceResolver($taxRateResolver);
        $price = $resolver->resolve($variant, $channel);

        self::assertNotNull($price);
        self::assertSame(1000, $price->gross);
        self::assertSame(1000, $price->net);
        self::assertNull($price->taxRate);
    }
}
