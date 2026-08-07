<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use EilingIo\SyliusTopiPlugin\Service\BuyNowOfferService;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function in_array;

/**
 * Backs the `<x-topi-checkout-button checkout-mode="cart">` on the cart page: creates
 * a "buy now" offer for the whole current cart, with no Sylius order involved yet
 * (see BuyNowOfferService) — the client redirects to the returned checkoutRedirectUrl.
 */
#[AsController]
#[Route('/topi-payment/buy-now/cart', name: 'topi_payment_buy_now_cart', methods: ['POST'])]
readonly class BuyNowCartOfferController
{
    public function __construct(
        private CartContextInterface $cartContext,
        private BuyNowOfferService $buyNowOfferService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var OrderInterface $cart */
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return new JsonResponse(['error' => 'Cart not found'], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($cart->getItems() as $item) {
            $variant = $item->getVariant();
            if ($variant === null) {
                continue;
            }

            $items[] = ['variant' => $variant, 'quantity' => $item->getQuantity()];
        }

        if ($items === [] || $cart->getChannel() === null) {
            return new JsonResponse(['error' => 'Cart is empty'], Response::HTTP_BAD_REQUEST);
        }

        $returnUrl = $this->urlGenerator->generate('topi_payment_buy_now_return', [], UrlGeneratorInterface::ABSOLUTE_URL);

        /** @var ChannelInterface $channel */
        $channel = $cart->getChannel();

        try {
            $createdOffer = $this->buyNowOfferService->create(
                $items,
                $channel,
                $request->getLocale(),
                $returnUrl,
                $returnUrl,
            );
        } catch (Throwable $e) {
            $this->logger->error('topi buy-now (cart) offer creation failed', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Could not create offer'], Response::HTTP_BAD_GATEWAY);
        }

        if ($createdOffer->checkoutRedirectUrl === ''
            || in_array($createdOffer->status, ['rejected', 'declined', 'voided', 'expired'], true)
        ) {
            $this->logger->warning('topi buy-now (cart) offer not actionable', [
                'offer_id' => $createdOffer->id ?? null,
                'status' => $createdOffer->status ?? null,
            ]);

            return new JsonResponse(['error' => 'Offer not actionable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['checkoutRedirectUrl' => $createdOffer->checkoutRedirectUrl]);
    }
}
