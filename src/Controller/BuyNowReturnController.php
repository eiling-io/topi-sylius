<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Where the customer lands after Topi's hosted checkout for the "Buy now"
 * checkout-button flow (BuyNowOfferService) — deliberately generic and
 * order-agnostic: the actual Sylius order is only created asynchronously once the
 * `order.created` webhook arrives (see WebhookController::createOrderFromPendingBuyNowAttempt),
 * which may not have happened yet by the time the browser redirects back here, so
 * there is no order to look up or session to set (contrast with ReturnController,
 * used by the normal checkout flow where the order already exists).
 */
#[AsController]
#[Route('/topi-payment/buy-now/return', name: 'topi_payment_buy_now_return')]
readonly class BuyNowReturnController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render(
            '@EilingIoSyliusTopiPlugin/shop/topi_elements/buy_now_processing.html.twig',
        ));
    }
}
