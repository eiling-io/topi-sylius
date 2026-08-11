<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Payum;

use Doctrine\ORM\EntityManagerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\CreatedOffer;
use EilingIo\SyliusTopiPlugin\PaymentStatus;
use EilingIo\SyliusTopiPlugin\Payum\CaptureAction;
use EilingIo\SyliusTopiPlugin\Service\OfferService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CaptureActionTest extends TestCase
{
    public function testExecuteRedirectsToTheCheckoutUrlAndStoresTheOfferOnSuccess(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('token-1');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(48);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn([]);

        $capturedDetails = null;
        $payment->expects($this->once())->method('setDetails')->willReturnCallback(function (array $details) use (&$capturedDetails) {
            $capturedDetails = $details;
        });

        $createdOffer = new CreatedOffer();
        $createdOffer->id = 'offer-1';
        $createdOffer->sellerOfferReference = 'seller-ref-1';
        $createdOffer->status = 'created';
        $createdOffer->checkoutRedirectUrl = 'https://checkout.topi-sandbox.eu/offer-1';

        $offerService = $this->createMock(OfferService::class);
        $offerService->expects($this->once())->method('createOffer')->willReturn($createdOffer);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://shop.example/topi-payment/return/token-1');

        $action = new CaptureAction($urlGenerator, $offerService, $entityManager, new NullLogger());

        try {
            $action->execute(new Capture($payment));
            self::fail('Expected an HttpRedirect reply.');
        } catch (HttpRedirect $reply) {
            self::assertSame('https://checkout.topi-sandbox.eu/offer-1', $reply->getUrl());
        }

        self::assertSame(PaymentStatus::OFFER_CREATED->value, $capturedDetails['status']);
        self::assertSame('offer-1', $capturedDetails['topi_offer_id']);
        self::assertSame('seller-ref-1', $capturedDetails['topi_seller_offer_reference']);
    }

    public function testExecuteRedirectsToTheReturnUrlWhenOfferCreationThrows(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('token-1');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->expects($this->never())->method('setDetails');

        $offerService = $this->createMock(OfferService::class);
        $offerService->method('createOffer')->willThrowException(
            new ConnectException('Connection failed', new GuzzleRequest('POST', 'offers')),
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://shop.example/topi-payment/return/token-1');

        $action = new CaptureAction($urlGenerator, $offerService, $entityManager, new NullLogger());

        try {
            $action->execute(new Capture($payment));
            self::fail('Expected an HttpRedirect reply.');
        } catch (HttpRedirect $reply) {
            self::assertSame('https://shop.example/topi-payment/return/token-1', $reply->getUrl());
        }
    }

    public function testExecuteRedirectsToTheReturnUrlWhenTheOfferIsRejected(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('token-1');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->expects($this->never())->method('setDetails');

        $createdOffer = new CreatedOffer();
        $createdOffer->status = 'rejected';
        $createdOffer->checkoutRedirectUrl = 'https://checkout.topi-sandbox.eu/offer-1';

        $offerService = $this->createMock(OfferService::class);
        $offerService->method('createOffer')->willReturn($createdOffer);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://shop.example/topi-payment/return/token-1');

        $action = new CaptureAction($urlGenerator, $offerService, $entityManager, new NullLogger());

        try {
            $action->execute(new Capture($payment));
            self::fail('Expected an HttpRedirect reply.');
        } catch (HttpRedirect $reply) {
            self::assertSame('https://shop.example/topi-payment/return/token-1', $reply->getUrl());
        }
    }

    public function testExecuteRejectsAnUnsupportedRequest(): void
    {
        $offerService = $this->createMock(OfferService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $action = new CaptureAction($urlGenerator, $offerService, $entityManager, new NullLogger());

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new \stdClass());
    }

    public function testSupportsACaptureRequestForAPayment(): void
    {
        $offerService = $this->createMock(OfferService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $action = new CaptureAction($urlGenerator, $offerService, $entityManager, new NullLogger());

        self::assertTrue($action->supports(new Capture($payment)));
        self::assertFalse($action->supports(new \stdClass()));
    }
}
