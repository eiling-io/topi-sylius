<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use EilingIo\SyliusTopiPlugin\Service\BuyNowOfferService;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function in_array;
use function max;

/**
 * Backs the `<x-topi-checkout-button checkout-mode="product">` on the PDP: creates a
 * "buy now" offer for a single variant/quantity, with no Sylius order involved yet
 * (see BuyNowOfferService) — the client redirects to the returned checkoutRedirectUrl.
 */
#[AsController]
#[Route('/topi-payment/buy-now/product/{variantCode}', name: 'topi_payment_buy_now_product', methods: ['POST'])]
readonly class BuyNowProductOfferController
{
    public function __construct(
        private ProductVariantRepositoryInterface $productVariantRepository,
        private ChannelContextInterface $channelContext,
        private BuyNowOfferService $buyNowOfferService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, string $variantCode): JsonResponse
    {
        $variant = $this->productVariantRepository->findOneBy(['code' => $variantCode]);

        if ($variant === null) {
            return new JsonResponse(['error' => 'Variant not found'], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true) ?: [];
        $quantity = max(1, (int) ($body['quantity'] ?? 1));

        $returnUrl = $this->urlGenerator->generate('topi_payment_buy_now_return', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $createdOffer = $this->buyNowOfferService->create(
                [['variant' => $variant, 'quantity' => $quantity]],
                $this->channelContext->getChannel(),
                $request->getLocale(),
                $returnUrl,
                $returnUrl,
            );
        } catch (Throwable $e) {
            $this->logger->error('topi buy-now (product) offer creation failed', [
                'variant' => $variantCode,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Could not create offer'], Response::HTTP_BAD_GATEWAY);
        }

        if ($createdOffer->checkoutRedirectUrl === ''
            || in_array($createdOffer->status, ['rejected', 'declined', 'voided', 'expired'], true)
        ) {
            $this->logger->warning('topi buy-now (product) offer not actionable', [
                'variant' => $variantCode,
                'offer_id' => $createdOffer->id ?? null,
                'status' => $createdOffer->status ?? null,
            ]);

            return new JsonResponse(['error' => 'Offer not actionable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['checkoutRedirectUrl' => $createdOffer->checkoutRedirectUrl]);
    }
}
