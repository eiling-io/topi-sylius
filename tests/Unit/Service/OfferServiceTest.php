<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreateOfferData;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreatedOffer;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferClient;
use EilingIo\SyliusTopiPlugin\Service\OfferService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;

/**
 * Feedback from a customer ("Jan Höchst") was that guest checkout didn't seem to be
 * possible. Nothing in this plugin actually gates Topi on having a registered
 * account — Sylius' guest checkout is the default, unmodified — but the one place
 * that *could* silently start depending on one is OfferService::createOffer(),
 * since it's the thing that turns a placed order into a Topi offer. This test is
 * the guardrail: it builds an order around a guest CustomerInterface (one whose
 * `getUser()` — the link to an actual account — is never even called) and asserts
 * offer creation succeeds and maps that guest's data correctly regardless.
 *
 * It also covers the actual reason a guest order can fail in practice: Topi rejects
 * an offer whose `company.name` is under 3 characters ("invalid_length"), and
 * Sylius' checkout address form leaves "Company" optional — see
 * OfferService::resolveCompanyName().
 */
final class OfferServiceTest extends TestCase
{
    public function testCreateOfferSucceedsForAGuestCustomerWithoutTouchingItsUserAccount(): void
    {
        $order = $this->buildOrder(billingCompany: null);

        // The crux of the guest-checkout guardrail: a customer with no linked user
        // account at all — and `getUser()` is asserted to never be called below, so
        // this would fail loudly if createOffer() ever started requiring one.
        $order->getCustomer()->expects($this->never())->method('getUser');

        $capturedData = $this->createOfferAndCapturePayload($order);

        self::assertSame('guest@example.com', $capturedData->customer->email);
        self::assertSame('Jane Guest', $capturedData->customer->fullName);
        // No company on the billing address (typical for a private/guest customer)
        // — falls back to the billing name so Topi's `invalid_length` (min 3 chars)
        // validation doesn't reject the offer outright.
        self::assertSame('Jane Guest', $capturedData->customer->company->name);
    }

    public function testCreateOfferKeepsTheRealCompanyNameWhenOneIsOnFile(): void
    {
        $order = $this->buildOrder(billingCompany: 'Acme GmbH');

        $capturedData = $this->createOfferAndCapturePayload($order);

        self::assertSame('Acme GmbH', $capturedData->customer->company->name);
    }

    private function createOfferAndCapturePayload(OrderInterface $order): CreateOfferData
    {
        $expectedOffer = new CreatedOffer();
        $expectedOffer->id = 'offer_123';
        $expectedOffer->status = 'created';
        $expectedOffer->checkoutRedirectUrl = 'https://checkout.topi-sandbox.eu/offer_123';

        $capturedData = null;

        $offerClient = $this->createMock(OfferClient::class);
        $offerClient->expects($this->once())
            ->method('createOffer')
            ->with($this->callback(function (CreateOfferData $data) use (&$capturedData): bool {
                $capturedData = $data;

                return true;
            }))
            ->willReturn($expectedOffer);

        $client = $this->createMock(Client::class);
        $client->method('offer')->willReturn($offerClient);

        $offerService = new OfferService($client);
        $createdOffer = $offerService->createOffer(
            $order,
            'https://shop.example/topi-payment/return/token',
            'https://shop.example/topi-payment/return/token',
        );

        self::assertSame($expectedOffer, $createdOffer);

        return $capturedData;
    }

    /**
     * @return OrderInterface&MockObject
     */
    private function buildOrder(?string $billingCompany): OrderInterface
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn('MUG-001');

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getUnitPrice')->willReturn(1000);
        $item->method('getQuantity')->willReturn(2);
        $item->method('getProductName')->willReturn('Topi Mug');
        $item->method('getTotal')->willReturn(2000);
        $item->method('getTaxTotal')->willReturn(380);
        $item->method('getVariant')->willReturn($variant);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getFullName')->willReturn('Jane Guest');
        $billingAddress->method('getCompany')->willReturn($billingCompany);
        $billingAddress->method('getCity')->willReturn('Berlin');
        $billingAddress->method('getPostcode')->willReturn('10115');
        $billingAddress->method('getCountryCode')->willReturn('DE');
        $billingAddress->method('getStreet')->willReturn('Musterstraße 1');

        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress->method('getCity')->willReturn('Berlin');
        $shippingAddress->method('getPostcode')->willReturn('10115');
        $shippingAddress->method('getCountryCode')->willReturn('DE');
        $shippingAddress->method('getStreet')->willReturn('Musterstraße 1');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('guest@example.com');

        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $shippingMethod->method('getCode')->willReturn('UPS');

        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($shippingMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('00000001');
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getShippingTotal')->willReturn(500);
        $order->method('getShippingTaxTotal')->willReturn(95);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        return $order;
    }
}
