<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\MoneyAmountWithOptionalTax;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\RecommendedRentalPricingRequest;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

use function max;
use function number_format;

#[AsController]
#[Route('/topi-payment/cart-pricing', name: 'topi_payment_cart_pricing', methods: ['GET'])]
readonly class CartRentalPricingController
{
    public function __construct(
        private CartContextInterface $cartContext,
        private Client $topiClient,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            /** @var OrderInterface $cart */
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return $this->notSupported();
        }

        $pricingRequests = $this->buildPricingRequests($cart);

        if ($pricingRequests === []) {
            return $this->notSupported();
        }

        try {
            $results = $this->topiClient->catalog()->listRecommendedRentalPrices($pricingRequests);
        } catch (GuzzleException $e) {
            $this->logger->warning('topi cart pricing API error', [
                'error' => $e->getMessage(),
            ]);

            return $this->notSupported();
        }

        $monthlyGross = 0;
        $duration = 0;

        foreach ($results as $result) {
            if (!$result->hasRentalTerms || $result->monthlyRentalTerms === null) {
                continue;
            }

            $monthlyGross += $result->monthlyRentalTerms->monthlyAmount->gross;
            $duration = max($duration, $result->monthlyRentalTerms->duration);
        }

        if ($monthlyGross <= 0) {
            return $this->notSupported();
        }

        return new JsonResponse([
            'isSupported' => true,
            'monthlyGross' => $monthlyGross,
            'monthlyGrossFormatted' => number_format($monthlyGross / 100, 2, ',', '.') . ' €',
            'duration' => $duration,
        ]);
    }

    /**
     * @return RecommendedRentalPricingRequest[]
     */
    private function buildPricingRequests(OrderInterface $cart): array
    {
        $pricingRequests = [];

        foreach ($cart->getItems() as $item) {
            if ($item->getTotal() <= 0) {
                continue;
            }

            $variant = $item->getVariant();

            if ($variant === null) {
                continue;
            }

            $price = new MoneyAmountWithOptionalTax();
            $price->currency = $cart->getCurrencyCode();
            $price->gross = $item->getTotal();
            $price->net = $item->getTotal() - $item->getTaxTotal();

            $productReference = new ProductReference();
            $productReference->source = 'syliusordernumbers';
            $productReference->reference = $variant->getCode();

            $pricingRequest = new RecommendedRentalPricingRequest();
            $pricingRequest->sellerProductReference = $productReference;
            $pricingRequest->price = $price;

            $pricingRequests[] = $pricingRequest;
        }

        return $pricingRequests;
    }

    private function notSupported(): JsonResponse
    {
        return new JsonResponse([
            'isSupported' => false,
        ]);
    }
}
