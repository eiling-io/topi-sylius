<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use DateInterval;
use DateTime;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreateOfferData;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreatedOffer;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferLinePayload;
use RuntimeException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Creates a topi offer for the "Buy now" checkout button (<x-topi-checkout-button>,
 * product or cart mode) — i.e. *before* any Sylius checkout has run, unlike
 * {@see OfferService} which maps an already-placed, already-addressed Sylius order.
 *
 * Deliberately omits customer/shipping/shippingAddress (see BaseOffer): Topi's own
 * hosted checkout collects them, and we read them back via the `order.created`
 * webhook / GET /orders/{id} in {@see BuyNowOrderCreator}, which is also what
 * actually creates the Sylius order — this service only reserves the attempt.
 */
final class BuyNowOfferService
{
    private const SELLER_PRODUCT_REFERENCE_SOURCE = 'syliusordernumbers';

    public function __construct(
        private readonly Client $client,
        private readonly VariantPriceResolver $priceResolver,
        private readonly PendingBuyNowAttemptStore $pendingAttemptStore,
    ) {
    }

    /**
     * @param array<int, array{variant: ProductVariantInterface, quantity: int}> $items
     */
    public function create(
        array $items,
        ChannelInterface $channel,
        string $localeCode,
        string $successRedirect,
        string $exitRedirect,
    ): CreatedOffer {
        $reference = 'buy-now-' . bin2hex(random_bytes(16));

        $offer = new CreateOfferData();
        $offer->sellerOfferReference = $reference;
        $offer->successRedirect = $successRedirect;
        $offer->exitRedirect = $exitRedirect;
        $offer->expiresAt = new DateTime()->add(new DateInterval('P1D'))->format('c');

        $snapshotItems = [];

        foreach ($items as $item) {
            $variant = $item['variant'];
            $quantity = $item['quantity'];

            $price = $this->priceResolver->resolve($variant, $channel);
            if ($price === null) {
                continue;
            }

            $line = new OfferLinePayload();
            $line->title = $variant->getProduct()?->getName() ?? $variant->getCode();
            $line->quantity = $quantity;

            $linePrice = new MoneyAmount();
            $linePrice->currency = $price->currency;
            $linePrice->gross = $price->gross * $quantity;
            $linePrice->net = $price->net * $quantity;
            $line->price = $linePrice;

            $productRef = new ProductReference();
            $productRef->source = self::SELLER_PRODUCT_REFERENCE_SOURCE;
            $productRef->reference = $variant->getCode();
            $line->sellerProductReference = $productRef;

            $offer->lines[] = $line;

            $snapshotItems[] = [
                'variantCode' => $variant->getCode(),
                'quantity' => $quantity,
            ];
        }

        if ($snapshotItems === []) {
            throw new RuntimeException('No priced items to create a topi buy-now offer for.');
        }

        $createdOffer = $this->client->offer()->createOffer($offer);

        $this->pendingAttemptStore->save($reference, [
            'channelCode' => (string) $channel->getCode(),
            'localeCode' => $localeCode,
            'items' => $snapshotItems,
        ]);

        return $createdOffer;
    }
}
