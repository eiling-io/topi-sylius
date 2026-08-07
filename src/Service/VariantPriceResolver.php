<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\MoneyAmountWithOptionalTax;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

/**
 * The Sylius 1.x app this plugin was ported from resolved gross/net prices via its own
 * B2B price-list machinery (`GrossPriceCalculator`/`NetPriceCalculator`/`PriceCollectionFilter`),
 * none of which exists in stock Sylius. This resolver is a plugin-native replacement built
 * directly on Sylius core's channel pricing + tax rate resolution.
 *
 * Currency is hardcoded to EUR, matching the source app: Topi's catalog/pricing API
 * rejects any other currency for a EUR-only seller account regardless of the shop's
 * own channel currency (confirmed against the sandbox: "Currency mismatch: Product
 * price must be in the seller's currency (EUR)").
 */
final class VariantPriceResolver
{
    public function __construct(
        private readonly TaxRateResolverInterface $taxRateResolver,
    ) {
    }

    public function resolve(ProductVariantInterface $variant, ChannelInterface $channel): ?MoneyAmountWithOptionalTax
    {
        $channelPricing = $variant->getChannelPricingForChannel($channel);
        $gross = $channelPricing?->getPrice();

        if ($gross === null) {
            return null;
        }

        $taxRate = $this->taxRateResolver->resolve($variant);
        $taxRateAmount = $taxRate?->getAmount();
        $net = $taxRateAmount !== null ? (int) round($gross / (1 + $taxRateAmount)) : $gross;

        $price = new MoneyAmountWithOptionalTax();
        $price->currency = 'EUR';
        $price->gross = $gross;
        $price->net = $net;
        $price->taxRate = $taxRateAmount !== null ? (int) round($taxRateAmount * 10000) : null;

        return $price;
    }
}
