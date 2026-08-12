<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EilingIo\SyliusTopiPlugin\Service\OfferService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function in_array;

/**
 * Backs the `<x-topi-checkout-button checkout-mode="cart">` shown on the
 * select_payment step once the customer has chosen Topi as their payment method
 * (see checkout_payment_button.html.twig) — a one-click alternative to clicking
 * "Next" and then "Place order" on the (skipped) complete step.
 *
 * Unlike the "Buy now" buttons (BuyNow*OfferController), a real Sylius order
 * already exists here with address and shipping already collected through the
 * normal checkout steps. The obvious way to complete it would be the API bundle's
 * own PickupCart/ChoosePaymentMethod/CompleteOrder commands (what BuyNowOrderCreator
 * uses) — but PickupCart's cart lookup explicitly excludes guest carts
 * (`createdByGuest = false` in OrderRepository::findLatestNotEmptyCartByChannelAndCustomer()),
 * since it's built for the headless API's own token-identified carts, not for
 * adopting an existing *web* session cart. Guest checkout being the very thing this
 * plugin had to go out of its way to keep working (see OfferService's company-name
 * fallback), silently only working for logged-in customers here wasn't acceptable —
 * so this drives the same state machine transitions directly instead:
 *
 *   1. sets Topi as the payment method on the cart's payment(s) and applies
 *      OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT — checking the "Topi
 *      Payment" radio only updates the browser's in-memory form state; without an
 *      actual select_payment form submission the order's checkout state server-side
 *      is still wherever the shipping step left it, and completing an order asserts
 *      payment has already been selected. This also makes clicking topi's button
 *      correct even if that assumption ever breaks: it explicitly sets topi_payment
 *      itself rather than trusting the radio;
 *   2. applies OrderCheckoutTransitions::TRANSITION_COMPLETE — the same transition
 *      the complete step's own form submission triggers (see the `state_machine`
 *      config on the `sylius_shop_checkout_complete` route), which is also what
 *      assigns the order its number and tokenValue;
 *   3. re-checks the now-completed order actually has Topi as its payment method
 *      (belt-and-suspenders alongside step 1 — this is the server-side guard
 *      Payum's own gateway resolution normally provides for the regular capture
 *      flow, which this endpoint bypasses);
 *   4. creates the topi offer for it through OfferService (address/shipping
 *      included, unlike BuyNowOfferService — Topi's hosted checkout has nothing
 *      left to collect).
 *
 * Deliberately simpler than CompleteOrderHandler: skips its promotion-integrity/
 * total-changed re-check (guards against a coupon expiring mid-checkout) — an edge
 * case Topi's own offer creation would separately surface as a price mismatch if it
 * ever occurred, not a silent inconsistency.
 */
#[AsController]
#[Route('/topi-payment/checkout-button', name: 'topi_payment_checkout_button', methods: ['POST'])]
readonly class CheckoutButtonOfferController
{
    private const TOPI_PAYMENT_METHOD_CODE = 'topi_payment';

    public function __construct(
        private CartContextInterface $cartContext,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
        private OfferService $offerService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            /** @var OrderInterface $order */
            $order = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return new JsonResponse(['error' => 'Cart not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var PaymentMethodInterface|null $topiPaymentMethod */
        $topiPaymentMethod = $this->paymentMethodRepository->findOneBy(['code' => self::TOPI_PAYMENT_METHOD_CODE]);

        if ($topiPaymentMethod === null) {
            $this->logger->error('topi checkout-button: topi_payment method not found');

            return new JsonResponse(['error' => 'Topi payment method is not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            foreach ($order->getPayments() as $payment) {
                $payment->setMethod($topiPaymentMethod);
            }

            if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT)) {
                $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
            }

            if (!$this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
                throw new RuntimeException('Order is not ready to be completed.');
            }

            $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);

            $this->entityManager->flush();
        } catch (Throwable $e) {
            $this->logger->error('topi checkout-button: order completion failed', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Could not complete order'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->hasTopiPaymentMethod($order)) {
            $this->logger->warning('topi checkout-button: order completed without topi as its payment method', [
                'order_number' => $order->getNumber(),
            ]);

            return new JsonResponse(['error' => 'Topi is not the selected payment method'], Response::HTTP_CONFLICT);
        }

        $returnUrl = $this->urlGenerator->generate(
            'topi_payment_return',
            ['tokenValue' => $order->getTokenValue()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        try {
            $createdOffer = $this->offerService->createOffer($order, $returnUrl, $returnUrl);
        } catch (Throwable $e) {
            $this->logger->error('topi checkout-button: offer creation failed', [
                'order_number' => $order->getNumber(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Could not create offer'], Response::HTTP_BAD_GATEWAY);
        }

        if ($createdOffer->checkoutRedirectUrl === ''
            || in_array($createdOffer->status, ['rejected', 'declined', 'voided', 'expired'], true)
        ) {
            $this->logger->warning('topi checkout-button: offer not actionable', [
                'order_number' => $order->getNumber(),
                'offer_id' => $createdOffer->id ?? null,
                'status' => $createdOffer->status ?? null,
            ]);

            return new JsonResponse(['error' => 'Offer not actionable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['checkoutRedirectUrl' => $createdOffer->checkoutRedirectUrl]);
    }

    private function hasTopiPaymentMethod(OrderInterface $order): bool
    {
        $lastPayment = $order->getPayments()->last();

        if ($lastPayment === false) {
            return false;
        }

        return $lastPayment->getMethod()?->getGatewayConfig()?->getFactoryName() === self::TOPI_PAYMENT_METHOD_CODE;
    }
}
