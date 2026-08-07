<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CustomerInfo;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferMapper;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\PostalAddress as TopiPostalAddress;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sylius\Bundle\ApiBundle\Command\Cart\AddItemToCart;
use Sylius\Bundle\ApiBundle\Command\Cart\PickupCart;
use Sylius\Bundle\ApiBundle\Command\Checkout\ChoosePaymentMethod;
use Sylius\Bundle\ApiBundle\Command\Checkout\ChooseShippingMethod;
use Sylius\Bundle\ApiBundle\Command\Checkout\CompleteOrder;
use Sylius\Bundle\ApiBundle\Command\Checkout\UpdateCart;
use Sylius\Component\Addressing\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

use function explode;
use function is_array;
use function sprintf;
use function trim;

/**
 * Builds and completes a full Sylius order from a "Buy now" attempt (see
 * BuyNowOfferService / PendingBuyNowAttemptStore) once Topi's `order.created`
 * webhook confirms the customer finished Topi's hosted checkout — this is the only
 * point where the actual Sylius order gets created for that flow; nothing exists
 * before this beyond the cached snapshot and the topi-side offer/order.
 *
 * Deliberately dispatches the exact same commands Sylius's own headless shop API
 * (`sylius/api-bundle`) uses for a normal checkout — PickupCart, AddItemToCart,
 * UpdateCart, ChooseShippingMethod, ChoosePaymentMethod, CompleteOrder — via the
 * `sylius.command_bus` (Symfony Messenger's default bus here, see
 * vendor/sylius/sylius .../CoreBundle/Resources/config/app/messenger.yaml) rather
 * than hand-rolling state-machine transitions: it's the officially supported way to
 * create/complete an order outside of an HTTP checkout request, and every step is
 * already validated/tested by Sylius itself.
 */
final class BuyNowOrderCreator
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Client $topiClient,
        private readonly CheapestShippingMethodResolver $shippingMethodResolver,
        private readonly FactoryInterface $addressFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array{channelCode: string, localeCode: string, items: array<int, array{variantCode: string, quantity: int}>} $pendingAttempt
     * @param array<string, mixed> $orderWebhookData
     */
    public function create(array $pendingAttempt, string $topiOrderId, string $sellerOfferReference, array $orderWebhookData): ?OrderInterface
    {
        [$customer, $shippingAddress] = $this->resolveCustomerAndAddress($topiOrderId, $orderWebhookData);

        if ($customer === null || $shippingAddress === null) {
            $this->logger->error('topi buy-now: order is missing customer/shipping_address, cannot create order', [
                'topi_order_id' => $topiOrderId,
                'seller_offer_reference' => $sellerOfferReference,
            ]);

            return null;
        }

        try {
            /** @var OrderInterface $cart */
            $cart = $this->dispatch(new PickupCart(
                $pendingAttempt['channelCode'],
                $pendingAttempt['localeCode'],
                $customer->email,
            ));

            foreach ($pendingAttempt['items'] as $item) {
                /** @var OrderInterface $cart */
                $cart = $this->dispatch(new AddItemToCart($cart->getTokenValue(), $item['variantCode'], $item['quantity']));
            }

            $address = $this->buildAddress($customer, $shippingAddress);

            /** @var OrderInterface $cart */
            $cart = $this->dispatch(new UpdateCart($cart->getTokenValue(), $customer->email, $address, clone $address));

            foreach ($cart->getShipments() as $shipment) {
                $method = $this->shippingMethodResolver->resolve($shipment);

                if ($method === null) {
                    $this->logger->error('topi buy-now: no eligible shipping method found', [
                        'seller_offer_reference' => $sellerOfferReference,
                        'shipment_id' => $shipment->getId(),
                    ]);

                    return null;
                }

                /** @var OrderInterface $cart */
                $cart = $this->dispatch(new ChooseShippingMethod($cart->getTokenValue(), $shipment->getId(), $method->getCode()));
            }

            foreach ($cart->getPayments() as $payment) {
                /** @var OrderInterface $cart */
                $cart = $this->dispatch(new ChoosePaymentMethod($cart->getTokenValue(), $payment->getId(), 'topi_payment'));
            }

            /** @var OrderInterface $order */
            $order = $this->dispatch(new CompleteOrder($cart->getTokenValue()));

            return $order;
        } catch (Throwable $e) {
            $this->logger->error('topi buy-now: order creation failed', [
                'seller_offer_reference' => $sellerOfferReference,
                'topi_order_id' => $topiOrderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Topi's webhook payload may already carry `customer`/`shipping_address` inline
     * (the order's full schema includes them); if not, fall back to fetching the
     * order explicitly.
     *
     * @param array<string, mixed> $orderWebhookData
     * @return array{0: CustomerInfo, 1: TopiPostalAddress}|array{0: null, 1: null}
     */
    private function resolveCustomerAndAddress(string $topiOrderId, array $orderWebhookData): array
    {
        $order = isset($orderWebhookData['customer'], $orderWebhookData['shipping_address'])
            ? null
            : $this->topiClient->order()->getOrder($topiOrderId);

        $customerData = $orderWebhookData['customer'] ?? null;
        $shippingAddressData = $orderWebhookData['shipping_address'] ?? null;

        if ($order !== null) {
            $customer = $order->customer;
            $shippingAddress = $order->shippingAddress;
        } else {
            $customer = is_array($customerData) ? OfferMapper::customerInfoFromArray($customerData) : null;
            $shippingAddress = is_array($shippingAddressData) ? OfferMapper::postalAddressFromArray($shippingAddressData) : null;
        }

        if ($customer === null || !isset($customer->email) || $shippingAddress === null || !isset($shippingAddress->city)) {
            return [null, null];
        }

        return [$customer, $shippingAddress];
    }

    private function buildAddress(CustomerInfo $customer, TopiPostalAddress $topiAddress): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();

        $fullName = $topiAddress->recipientName ?? ($customer->fullName ?? $customer->email);
        [$firstName, $lastName] = $this->splitName($fullName);

        $address->setFirstName($firstName);
        $address->setLastName($lastName);
        $address->setCompany(isset($customer->company) ? $customer->company->name : null);
        $address->setStreet($topiAddress->line1);
        $address->setCity($topiAddress->city);
        $address->setPostcode($topiAddress->postalCode);
        $address->setCountryCode($topiAddress->countryCode);

        if ($topiAddress->region !== null) {
            $address->setProvinceName($topiAddress->region);
        }

        return $address;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            $parts[0] !== '' ? $parts[0] : 'N/A',
            $parts[1] ?? $parts[0],
        ];
    }

    private function dispatch(object $command): mixed
    {
        /** @var Envelope $envelope */
        $envelope = $this->commandBus->dispatch($command);

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new RuntimeException(sprintf('Command "%s" was not handled.', $command::class));
        }

        return $handledStamp->getResult();
    }
}
