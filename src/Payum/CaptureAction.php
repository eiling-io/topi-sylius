<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Payum;

use Doctrine\ORM\EntityManagerInterface;
use EilingIo\SyliusTopiPlugin\PaymentStatus;
use EilingIo\SyliusTopiPlugin\Service\OfferService;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function in_array;

#[Autoconfigure(public: true)]
#[AutoconfigureTag('payum.action', [
    'factory' => 'topi_payment',
    'alias' => 'payum.action.capture',
])]
readonly class CaptureAction implements ActionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private OfferService $offerService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        $this->logger->info('topi CaptureAction called', [
            'payment_id' => $payment->getId(),
        ]);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $returnUrl = $this->urlGenerator->generate('topi_payment_return', [
            'tokenValue' => $order->getTokenValue(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // The order is already placed at this point; both the Topi exit redirect and any
        // local failure must land the customer on the thank-you page (via the return
        // controller), never back on a /checkout/* route — the checkout resolver would
        // otherwise bounce a customer with no active cart to the empty cart summary.
        $successRedirect = $returnUrl;
        $errorRedirect = $returnUrl;

        try {
            $createdOffer = $this->offerService->createOffer($order, $successRedirect, $errorRedirect);
        } catch (GuzzleException|JsonException $e) {
            $this->logger->error('topi offer creation failed', [
                'order_id' => $order->getId(),
                'payment_id' => $payment->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new HttpRedirect($errorRedirect);
        }

        if ($createdOffer->checkoutRedirectUrl === ''
            || in_array($createdOffer->status, ['rejected', 'declined', 'voided', 'expired'], true)
        ) {
            $this->logger->warning('topi offer not actionable', [
                'order_id' => $order->getId(),
                'payment_id' => $payment->getId(),
                'offer_id' => $createdOffer->id ?? null,
                'status' => $createdOffer->status ?? null,
                'checkout_url' => $createdOffer->checkoutRedirectUrl,
            ]);

            throw new HttpRedirect($errorRedirect);
        }

        // No dedicated Doctrine columns exist for the Topi identifiers in this plugin (unlike
        // the Sylius 1.x app it was ported from, which extended its own Payment entity) — they
        // are kept inside Payum's own `details` blob instead, alongside everything else.
        $payment->setDetails(array_merge($payment->getDetails(), [
            'status' => PaymentStatus::OFFER_CREATED->value,
            'topi_offer_id' => $createdOffer->id,
            'topi_seller_offer_reference' => $createdOffer->sellerOfferReference ?? $order->getNumber(),
        ]));

        $this->entityManager->flush();

        throw new HttpRedirect($createdOffer->checkoutRedirectUrl);
    }

    public function supports($request): bool
    {
        return $request instanceof Capture && $request->getModel() instanceof PaymentInterface;
    }
}
