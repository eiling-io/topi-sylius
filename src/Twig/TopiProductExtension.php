<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Twig;

use EilingIo\SyliusTopiPlugin\MinOrderValue;
use EilingIo\SyliusTopiPlugin\Service\VariantPriceResolver;
use RuntimeException;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * The Sylius 1.x app this plugin was ported from also gated `is_topi_product()` on a
 * hardcoded tenant/channel enum (`ChannelCode::OFFICE_PARTNER`) — that concept doesn't
 * exist outside that multi-shop monolith, so it's dropped here: any channel qualifies
 * as long as `$topiEnabled` is on and the variant clears the minimum order value.
 */
final class TopiProductExtension extends AbstractExtension implements GlobalsInterface
{
    private const SELLER_PRODUCT_REFERENCE_SOURCE = 'syliusordernumbers';

    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly VariantPriceResolver $priceResolver,
        private readonly bool $topiEnabled,
        #[Autowire(env: 'TOPI_WIDGET_ID')]
        private readonly string $topiWidgetId,
        #[Autowire(env: 'bool:TOPI_ENABLE_LIVE')]
        private readonly bool $topiEnableLive,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_topi_product', $this->isTopiProduct(...)),
            new TwigFunction('topi_pdp_item', $this->getPdpItem(...)),
        ];
    }

    /**
     * Exposed as Twig globals so the Topi Elements script include (init.html.twig,
     * hooked into `sylius_shop.base#javascripts`) doesn't need its own service wiring.
     */
    public function getGlobals(): array
    {
        return [
            'topi_enabled' => $this->topiEnabled && $this->topiWidgetId !== '',
            'topi_widget_id' => $this->topiWidgetId,
            'topi_enable_live' => $this->topiEnableLive,
        ];
    }

    public function isTopiProduct(ProductVariantInterface $variant): bool
    {
        if ($this->topiEnabled === false) {
            return false;
        }

        if (!$variant->isEnabled()) {
            return false;
        }

        return $this->reachesMinOrderValue($variant);
    }

    private function reachesMinOrderValue(ProductVariantInterface $variant): bool
    {
        $price = $this->priceResolver->resolve($variant, $this->channelContext->getChannel());

        return $price !== null && $price->gross >= MinOrderValue::CENTS;
    }

    /**
     * Price is always per-unit — Topi Elements multiplies it by `quantity` itself
     * ("To render the price for just 1 quantity of this item, set quantity: 1").
     * $quantity defaults to 1 for the PDP/listing badges; the cart badge and rental
     * overview pass the actual line quantity.
     *
     * @return array{
     *     price: array{currency: string, gross: int, net: int, taxRate?: int},
     *     quantity: int,
     *     sellerProductReference: array{reference: string, source: string},
     * }
     */
    public function getPdpItem(ProductVariantInterface $variant, int $quantity = 1): array
    {
        $price = $this->priceResolver->resolve($variant, $this->channelContext->getChannel());

        if ($price === null) {
            throw new RuntimeException('topi PDP item requires an active channel price on the variant.');
        }

        $priceData = [
            'currency' => $price->currency,
            'gross' => $price->gross,
            'net' => $price->net,
        ];

        if ($price->taxRate !== null) {
            $priceData['taxRate'] = $price->taxRate;
        }

        return [
            'price' => $priceData,
            'quantity' => $quantity,
            'sellerProductReference' => [
                'reference' => $variant->getCode(),
                'source' => self::SELLER_PRODUCT_REFERENCE_SOURCE,
            ],
        ];
    }
}
