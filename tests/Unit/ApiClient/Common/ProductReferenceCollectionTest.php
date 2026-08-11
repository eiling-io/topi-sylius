<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Common;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReferenceCollection;
use PHPUnit\Framework\TestCase;

final class ProductReferenceCollectionTest extends TestCase
{
    public function testAddAndCount(): void
    {
        $collection = new ProductReferenceCollection();
        $collection->add($this->reference('MUG-001'));
        $collection->add($this->reference('MUG-002'));

        self::assertCount(2, $collection);
        self::assertSame(2, $collection->count());
    }

    public function testAddIgnoresTheSameInstanceTwice(): void
    {
        $collection = new ProductReferenceCollection();
        $reference = $this->reference('MUG-001');

        $collection->add($reference);
        $collection->add($reference);

        self::assertCount(1, $collection);
    }

    /**
     * Documents the collection's actual (surprising) behavior rather than the
     * intuitive one: `remove()`'s `in_array(...) → return` short-circuits *before*
     * ever filtering, so a reference that *is* present is left untouched, and one
     * that *isn't* present hits a filter that (correctly, but pointlessly) removes
     * nothing either — i.e. remove() is currently a no-op either way. Not fixed here
     * since this test is about coverage, not behavior changes; flagged separately.
     */
    public function testRemoveIsCurrentlyANoOpForAPresentReference(): void
    {
        $collection = new ProductReferenceCollection();
        $reference = $this->reference('MUG-001');
        $collection->add($reference);

        $collection->remove($reference);

        self::assertCount(1, $collection);
        self::assertSame([$reference], $collection->getProductReferences());
    }

    public function testRemoveOfAnAbsentReferenceLeavesTheCollectionUnchanged(): void
    {
        $collection = new ProductReferenceCollection();
        $present = $this->reference('MUG-001');
        $collection->add($present);

        $collection->remove($this->reference('MUG-999'));

        self::assertSame([$present], $collection->getProductReferences());
    }

    public function testGetProductReferencesReturnsAddedItemsInOrder(): void
    {
        $collection = new ProductReferenceCollection();
        $first = $this->reference('MUG-001');
        $second = $this->reference('MUG-002');
        $collection->add($first);
        $collection->add($second);

        self::assertSame([$first, $second], $collection->getProductReferences());
    }

    private function reference(string $code): ProductReference
    {
        $reference = new ProductReference();
        $reference->source = 'syliusordernumbers';
        $reference->reference = $code;

        return $reference;
    }
}
