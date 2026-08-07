<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Controller;

use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\RecommendedRentalPricingRequest;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use EilingIo\SyliusTopiPlugin\Service\VariantPriceResolver;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[AsController]
#[Route('/topi-payment/product-pricing/{variantCode}', name: 'topi_payment_product_pricing', methods: ['GET'])]
readonly class ProductPricingController
{
    public function __construct(
        private ProductVariantRepositoryInterface $productVariantRepository,
        private ChannelContextInterface $channelContext,
        private VariantPriceResolver $priceResolver,
        private Client $topiClient,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(string $variantCode): JsonResponse
    {
        $variant = $this->productVariantRepository->findOneBy(['code' => $variantCode]);

        if ($variant === null) {
            return new JsonResponse([
                'error' => 'Variant not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $pricePayload = $this->priceResolver->resolve($variant, $this->channelContext->getChannel());
        } catch (Throwable $e) {
            $this->logger->warning('topi widget: could not resolve price', [
                'variant' => $variantCode,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'isSupported' => false,
            ]);
        }

        if ($pricePayload === null) {
            return new JsonResponse([
                'isSupported' => false,
            ]);
        }

        $productRef = new ProductReference();
        $productRef->source = 'syliusordernumbers';
        $productRef->reference = $variantCode;

        $pricingRequest = new RecommendedRentalPricingRequest();
        $pricingRequest->sellerProductReference = $productRef;
        $pricingRequest->price = $pricePayload;

        try {
            $results = $this->topiClient->catalog()->listRecommendedRentalPrices([$pricingRequest]);
        } catch (GuzzleException $e) {
            $this->logger->warning('topi pricing API error', [
                'variant' => $variantCode,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                [
                    'isSupported' => false,
                    'variant' => $variantCode,
                    'error' => $e->getMessage(),
                ],
            );
        }

        $response = $results[0] ?? null;

        if ($response === null || !$response->hasRentalTerms || $response->monthlyRentalTerms === null) {
            return new JsonResponse([
                'isSupported' => false,
            ]);
        }

        $term = $response->monthlyRentalTerms;
        $monthlyGross = $term->monthlyAmount->gross;

        return new JsonResponse([
            'isSupported' => true,
            'minMonthlyGross' => $monthlyGross,
            'minMonthlyGrossFormatted' => number_format($monthlyGross / 100, 2, ',', '.') . ' €',
            'minDuration' => $term->duration,
            'summary' => $response->summary,
            'contractTerms' => [
                [
                    'duration' => $term->duration,
                    'monthlyGross' => $monthlyGross,
                    'monthlyGrossFormatted' => number_format($monthlyGross / 100, 2, ',', '.') . ' €',
                ],
            ],
        ]);
    }
}
