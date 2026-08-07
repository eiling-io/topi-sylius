<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EilingIo\SyliusTopiPlugin\PaymentSettlement;
use EilingIo\SyliusTopiPlugin\PaymentStatus;
use EilingIo\SyliusTopiPlugin\Service\BuyNowOrderCreator;
use EilingIo\SyliusTopiPlugin\Service\PendingBuyNowAttemptStore;
use EilingIo\SyliusTopiPlugin\Service\WebhookVerificationService;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

use function array_key_exists;
use function array_keys;
use function in_array;
use function is_string;

/**
 * Looks payments up by seller offer reference == Sylius order number (set in
 * {@see \EilingIo\SyliusTopiPlugin\Service\OfferService}), since this plugin has no
 * dedicated `topi_seller_offer_reference` column to query against (the app this was
 * ported from extended its own Payment entity; a standalone plugin keeps everything
 * inside Payum's `details` blob instead — see {@see \EilingIo\SyliusTopiPlugin\Payum\CaptureAction}).
 */
#[AsController]
#[Route('/topi-payment/webhook', name: 'topi_payment_webhook', methods: ['POST'])]
readonly class WebhookController
{
    public function __construct(
        private WebhookVerificationService $webhookVerificationService,
        private OrderRepositoryInterface $orderRepository,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $orm,
        private PendingBuyNowAttemptStore $pendingBuyNowAttemptStore,
        private BuyNowOrderCreator $buyNowOrderCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $content = $request->getContent();

        try {
            $data = $this->webhookVerificationService->verify($content, [
                'svix-id' => $request->headers->get('svix-id'),
                'svix-timestamp' => $request->headers->get('svix-timestamp'),
                'svix-signature' => $request->headers->get('svix-signature'),
            ]);
        } catch (RuntimeException|JsonException $e) {
            $this->logger->error('topi webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Verification failed',
            ], Response::HTTP_BAD_REQUEST);
        }

        $event = $this->resolveEvent($data);
        if ($event === null) {
            $this->logger->error('topi webhook: unable to resolve event from payload', [
                'keys' => array_keys($data),
            ]);

            return new JsonResponse([
                'status' => 'error',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('topi webhook received', [
            'event' => $event,
            'data' => $data,
        ]);

        $eventParent = substr($event, 0, (int) strpos($event, '.'));
        match ($eventParent) {
            'offer' => $this->handleOfferEvent($event, $data),
            'order' => $this->handleOrderEvent($event, $data),
            default => null,
        };

        return new JsonResponse([
            'status' => 'success',
        ], Response::HTTP_CREATED);
    }

    /**
     * Topi delivers every event to a single URL; the event type is derived from
     * the resource shape (orders carry an `offer_id`) and its lifecycle `status`.
     *
     * @param array<string, mixed> $data
     */
    private function resolveEvent(array $data): ?string
    {
        $status = $data['status'] ?? null;
        if (!is_string($status) || $status === '') {
            return null;
        }

        $resource = array_key_exists('offer_id', $data) ? 'order' : 'offer';

        return $resource . '.' . $status;
    }

    /**
     * @param array<string, mixed> $offerData
     */
    private function handleOfferEvent(string $event, array $offerData): void
    {
        $sellerOfferReference = $offerData['seller_offer_reference'] ?? null;
        if (!is_string($sellerOfferReference) || $sellerOfferReference === '') {
            return;
        }

        $payment = $this->findPaymentBySellerOfferReference($sellerOfferReference);
        if ($payment === null) {
            $this->logger->warning('topi webhook: no payment found for offer', [
                'seller_offer_reference' => $sellerOfferReference,
                'event' => $event,
            ]);

            return;
        }

        $this->applyEvent($payment, $event);
        $this->orm->flush();
    }

    /**
     * @param array<string, mixed> $orderData
     */
    private function handleOrderEvent(string $event, array $orderData): void
    {
        $orderId = $orderData['id'] ?? null;
        $sellerOfferReference = $orderData['seller_offer_reference'] ?? null;

        if ($orderId === null || !is_string($sellerOfferReference) || $sellerOfferReference === '') {
            return;
        }

        $payment = $this->findPaymentBySellerOfferReference($sellerOfferReference);

        if ($payment === null) {
            $payment = $this->createOrderFromPendingBuyNowAttempt($sellerOfferReference, $orderId, $orderData);
        }

        if ($payment === null) {
            $this->logger->critical('topi order webhook: no payment found for offer', [
                'order_id' => $orderId,
                'seller_offer_reference' => $sellerOfferReference,
                'event' => $event,
            ]);

            return;
        }

        $payment->setDetails(array_merge($payment->getDetails(), [
            'topi_order_id' => $orderId,
            'topi_order_status' => $orderData['status'] ?? null,
        ]));

        $this->applyEvent($payment, $event);
        $this->orm->flush();
    }

    private function findPaymentBySellerOfferReference(string $sellerOfferReference): ?PaymentInterface
    {
        $order = $this->orderRepository->findOneBy(['number' => $sellerOfferReference]);

        return $order?->getLastPayment();
    }

    /**
     * The "Buy now" checkout-button flow (BuyNowOfferService) never places a Sylius
     * order up front — this is where one finally gets created, once Topi's hosted
     * checkout has collected the customer/address and confirmed the order.
     *
     * @param array<string, mixed> $orderData
     */
    private function createOrderFromPendingBuyNowAttempt(string $sellerOfferReference, string $orderId, array $orderData): ?PaymentInterface
    {
        $pendingAttempt = $this->pendingBuyNowAttemptStore->get($sellerOfferReference);
        if ($pendingAttempt === null) {
            return null;
        }

        $order = $this->buyNowOrderCreator->create($pendingAttempt, $orderId, $sellerOfferReference, $orderData);
        if ($order === null) {
            return null;
        }

        $this->pendingBuyNowAttemptStore->delete($sellerOfferReference);

        return $order->getLastPayment();
    }

    private function applyEvent(PaymentInterface $payment, string $event): void
    {
        $status = $this->statusForEvent($event);
        if ($status === null) {
            return;
        }

        $transition = $this->transitionForStatus($status);

        if ($transition === null) {
            if ($this->isOpen($payment)) {
                $this->recordStatus($payment, $status);
            }

            return;
        }

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            $this->logger->warning('topi webhook: transition not applicable for current payment state', [
                'payment_id' => $payment->getId(),
                'state' => $payment->getState(),
                'transition' => $transition,
                'status' => $status->value,
                'event' => $event,
            ]);

            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
        $this->recordStatus($payment, $status);
    }

    private function recordStatus(PaymentInterface $payment, PaymentStatus $status): void
    {
        $payment->setDetails(array_merge($payment->getDetails(), [
            'status' => $status->value,
        ]));
    }

    private function isOpen(PaymentInterface $payment): bool
    {
        return in_array($payment->getState(), [
            PaymentInterface::STATE_NEW,
            PaymentInterface::STATE_PROCESSING,
        ], true);
    }

    private function statusForEvent(string $event): ?PaymentStatus
    {
        return match ($event) {
            'offer.created' => PaymentStatus::OFFER_CREATED,
            'offer.pending_review' => PaymentStatus::OFFER_PENDING_REVIEW,
            'offer.accepted' => PaymentStatus::OFFER_ACCEPTED,
            'offer.declined', 'offer.rejected' => PaymentStatus::OFFER_DECLINED,
            'offer.expired' => PaymentStatus::OFFER_EXPIRED,
            'offer.voided' => PaymentStatus::OFFER_VOIDED,
            'order.created' => PaymentStatus::ORDER_CREATED,
            'order.confirmed', 'order.acknowledged' => PaymentStatus::ORDER_CONFIRMED,
            'order.completed' => PaymentStatus::ORDER_COMPLETED,
            'order.canceled' => PaymentStatus::ORDER_CANCELED,
            'order.rejected' => PaymentStatus::ORDER_REJECTED,
            default => null,
        };
    }

    private function transitionForStatus(PaymentStatus $status): ?string
    {
        return match ($status->settlement()) {
            PaymentSettlement::PENDING => null,
            PaymentSettlement::CAPTURED => PaymentTransitions::TRANSITION_COMPLETE,
            PaymentSettlement::FAILED => PaymentTransitions::TRANSITION_FAIL,
            PaymentSettlement::CANCELED => PaymentTransitions::TRANSITION_CANCEL,
        };
    }
}
