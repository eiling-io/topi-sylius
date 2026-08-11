<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use EilingIo\SyliusTopiPlugin\Service\CheapestShippingMethodResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final class CheapestShippingMethodResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenNoMethodIsSupported(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);

        $methodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $methodsResolver->method('getSupportedMethods')->willReturn([]);

        $calculator = $this->createMock(DelegatingCalculatorInterface::class);
        $calculator->expects($this->never())->method('calculate');

        $resolver = new CheapestShippingMethodResolver($methodsResolver, $calculator);

        self::assertNull($resolver->resolve($shipment));
    }

    public function testResolvePicksTheCheapestMethodAndRestoresTheOriginal(): void
    {
        $originalMethod = $this->createMock(ShippingMethodInterface::class);
        $cheapMethod = $this->createMock(ShippingMethodInterface::class);
        $expensiveMethod = $this->createMock(ShippingMethodInterface::class);

        $setMethods = [];
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($originalMethod);
        $shipment->method('setMethod')->willReturnCallback(function ($method) use (&$setMethods) {
            $setMethods[] = $method;
        });

        $methodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $methodsResolver->method('getSupportedMethods')->willReturn([$expensiveMethod, $cheapMethod]);

        // The calculator only ever sees the same $shipment mock (its "current
        // method" isn't independently inspectable here), so the cheapest-price
        // sequence is driven by call order instead: first call (after the
        // expensive method is set) returns 1000, second (cheap method) returns 500.
        $calculator = $this->createMock(DelegatingCalculatorInterface::class);
        $calculator->method('calculate')->willReturnOnConsecutiveCalls(1000, 500);

        $resolver = new CheapestShippingMethodResolver($methodsResolver, $calculator);

        $result = $resolver->resolve($shipment);

        self::assertSame($cheapMethod, $result);
        self::assertSame([$expensiveMethod, $cheapMethod, $originalMethod], $setMethods);
    }
}
