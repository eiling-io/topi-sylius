<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

/**
 * Used by BuyNowOrderCreator to auto-pick a shipping method for the "Buy now"
 * checkout-button flow, where the customer never sees our own shipping-selection
 * step (Topi's hosted checkout only collects the address). The calculator interface
 * only computes a cost for whatever method is *currently* set on the shipment, so
 * comparing candidates means setting each one in turn — done to a temporary shipment
 * state that's restored before returning.
 */
final class CheapestShippingMethodResolver
{
    public function __construct(
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly DelegatingCalculatorInterface $calculator,
    ) {
    }

    public function resolve(ShipmentInterface $shipment): ?ShippingMethodInterface
    {
        $methods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

        if ($methods === []) {
            return null;
        }

        $originalMethod = $shipment->getMethod();
        $cheapestMethod = null;
        $cheapestCost = null;

        foreach ($methods as $method) {
            $shipment->setMethod($method);
            $cost = $this->calculator->calculate($shipment);

            if ($cheapestCost === null || $cost < $cheapestCost) {
                $cheapestCost = $cost;
                $cheapestMethod = $method;
            }
        }

        $shipment->setMethod($originalMethod);

        return $cheapestMethod;
    }
}
