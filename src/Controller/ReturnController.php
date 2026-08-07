<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/topi-payment/return/{tokenValue}', name: 'topi_payment_return')]
readonly class ReturnController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(Request $request, string $tokenValue): RedirectResponse
    {
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        if ($order !== null) {
            $request->getSession()->set('sylius_order_id', $order->getId());
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_order_thank_you'));
    }
}
