<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use EilingIo\SyliusTopiPlugin\Service\PendingBuyNowAttemptStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class PendingBuyNowAttemptStoreTest extends TestCase
{
    public function testSaveThenGetRoundTripsTheSnapshot(): void
    {
        $store = new PendingBuyNowAttemptStore(new ArrayAdapter());

        $snapshot = [
            'channelCode' => 'FASHION_WEB',
            'localeCode' => 'de_DE',
            'items' => [['variantCode' => 'MUG-001', 'quantity' => 2]],
        ];

        $store->save('buy-now-abc', $snapshot);

        self::assertSame($snapshot, $store->get('buy-now-abc'));
    }

    public function testGetReturnsNullForAnUnknownReference(): void
    {
        $store = new PendingBuyNowAttemptStore(new ArrayAdapter());

        self::assertNull($store->get('never-saved'));
    }

    public function testDeleteRemovesTheSnapshot(): void
    {
        $store = new PendingBuyNowAttemptStore(new ArrayAdapter());
        $store->save('buy-now-abc', ['channelCode' => 'FASHION_WEB', 'localeCode' => 'de_DE', 'items' => []]);

        $store->delete('buy-now-abc');

        self::assertNull($store->get('buy-now-abc'));
    }

    public function testCacheKeySanitizesReferencesWithUnexpectedCharacters(): void
    {
        // PSR-6 keys forbid characters like "{}()/\@:" — a reference containing any
        // would otherwise blow up inside the cache adapter rather than in our own
        // code, which is exactly what cacheKey()'s sanitizing is for.
        $store = new PendingBuyNowAttemptStore(new ArrayAdapter());
        $snapshot = ['channelCode' => 'FASHION_WEB', 'localeCode' => 'de_DE', 'items' => []];

        $store->save('buy-now-abc/{weird}@ref', $snapshot);

        self::assertSame($snapshot, $store->get('buy-now-abc/{weird}@ref'));
    }
}
