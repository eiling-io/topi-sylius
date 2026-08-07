<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use EilingIo\SyliusTopiPlugin\Service\OfferService;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function in_array;

#[AsController]
#[Route('/topi-payment/create-offer/{tokenValue}', name: 'topi_payment_create_offer', methods: ['POST'])]
readonly class CreateOfferController
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OfferService $offerService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(string $tokenValue): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        if ($order === null) {
            return new Response('Order not found', Response::HTTP_NOT_FOUND);
        }

        $successRedirect = $this->urlGenerator->generate(
            'topi_payment_return',
            compact('tokenValue'),
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $errorRedirect = $this->urlGenerator->generate(
            'sylius_shop_order_thank_you',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        try {
            $createdOffer = $this->offerService->createOffer($order, $successRedirect, $errorRedirect);
        } catch (RequestException $e) {
            $this->logger->error('topi offer creation failed on thank-you page', [
                'order_number' => $order->getNumber(),
                'error' => $e->getMessage(),
            ]);

            return new RedirectResponse($errorRedirect);
        }

        if (empty($createdOffer->checkoutRedirectUrl)
            || in_array($createdOffer->status, ['rejected', 'declined', 'voided', 'expired'], true)
        ) {
            $this->logger->warning('topi offer not actionable on thank-you page', [
                'order_number' => $order->getNumber(),
                'offer_id' => $createdOffer->id ?? null,
                'status' => $createdOffer->status ?? null,
            ]);

            return new RedirectResponse($errorRedirect);
        }

        return new RedirectResponse($createdOffer->checkoutRedirectUrl);
    }
}
