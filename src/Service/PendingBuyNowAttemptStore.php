<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stashes a snapshot of what a "Buy now" offer (see BuyNowOfferService) was created
 * for — channel, locale, and line items — so the topi `order.created` webhook can
 * build the actual Sylius order later (see BuyNowOrderCreator), keyed by the offer's
 * `sellerOfferReference`.
 *
 * A cache pool rather than a Doctrine entity/migration: the record is short-lived
 * (Topi's own offer expires after 1 day) and purely a staging area, not business data
 * that needs to survive indefinitely or be queried — keeping with this plugin's
 * general approach of avoiding new schema where a TTL'd cache entry does the job.
 */
final class PendingBuyNowAttemptStore
{
    private const TTL_SECONDS = 60 * 60 * 24 * 2;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param array{channelCode: string, localeCode: string, items: array<int, array{variantCode: string, quantity: int}>} $snapshot
     */
    public function save(string $reference, array $snapshot): void
    {
        $item = $this->cache->getItem($this->cacheKey($reference));
        $item->set($snapshot);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);
    }

    /**
     * @return array{channelCode: string, localeCode: string, items: array<int, array{variantCode: string, quantity: int}>}|null
     */
    public function get(string $reference): ?array
    {
        $item = $this->cache->getItem($this->cacheKey($reference));

        return $item->isHit() ? $item->get() : null;
    }

    public function delete(string $reference): void
    {
        $this->cache->deleteItem($this->cacheKey($reference));
    }

    private function cacheKey(string $reference): string
    {
        // PSR-6 keys forbid a handful of characters; our own references are always
        // "buy-now-<hex>" already, this is just a defensive sanitize.
        return 'topi_buy_now_attempt_' . preg_replace('/[^A-Za-z0-9_.]/', '_', $reference);
    }
}
