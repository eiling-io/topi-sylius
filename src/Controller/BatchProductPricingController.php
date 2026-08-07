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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;

#[AsController]
#[Route('/topi-payment/product-pricing', name: 'topi_payment_product_pricing_batch', methods: ['POST'])]
readonly class BatchProductPricingController
{
    public function __construct(
        private ProductVariantRepositoryInterface $productVariantRepository,
        private ChannelContextInterface $channelContext,
        private VariantPriceResolver $priceResolver,
        private Client $topiClient,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $variantCodes = $body['variantCodes'] ?? [];

        if (empty($variantCodes) || !is_array($variantCodes)) {
            return new JsonResponse([
                'error' => 'variantCodes required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $channel = $this->channelContext->getChannel();
        $result = [];
        $pricingRequests = [];

        foreach ($variantCodes as $code) {
            $variant = $this->productVariantRepository->findOneBy(['code' => $code]);

            if ($variant === null) {
                $result[$code] = [
                    'isSupported' => false,
                ];

                continue;
            }

            $pricePayload = $this->priceResolver->resolve($variant, $channel);

            if ($pricePayload === null) {
                $result[$code] = [
                    'isSupported' => false,
                ];

                continue;
            }

            $productRef = new ProductReference();
            $productRef->source = 'syliusordernumbers';
            $productRef->reference = $code;

            $pricingRequest = new RecommendedRentalPricingRequest();
            $pricingRequest->sellerProductReference = $productRef;
            $pricingRequest->price = $pricePayload;

            $pricingRequests[$code] = $pricingRequest;
        }

        if (!empty($pricingRequests)) {
            try {
                $topiResults = $this->topiClient->catalog()->listRecommendedRentalPrices(array_values($pricingRequests));

                foreach ($topiResults as $topiResult) {
                    $code = $topiResult->sellerProductReference->reference;

                    if (!$topiResult->hasRentalTerms || $topiResult->monthlyRentalTerms === null) {
                        $result[$code] = [
                            'isSupported' => false,
                        ];

                        continue;
                    }

                    $term = $topiResult->monthlyRentalTerms;
                    $monthlyGross = $term->monthlyAmount->gross;

                    $result[$code] = [
                        'isSupported' => true,
                        'minMonthlyGross' => $monthlyGross,
                        'minMonthlyGrossFormatted' => number_format($monthlyGross / 100, 2, ',', '.') . ' €',
                        'minDuration' => $term->duration,
                        'summary' => $topiResult->summary,
                    ];
                }

                foreach (array_keys($pricingRequests) as $code) {
                    if (!isset($result[$code])) {
                        $result[$code] = [
                            'isSupported' => false,
                        ];
                    }
                }
            } catch (GuzzleException $e) {
                $this->logger->warning('topi batch pricing API error', [
                    'error' => $e->getMessage(),
                ]);
                foreach (array_keys($pricingRequests) as $code) {
                    $result[$code] = [
                        'isSupported' => false,
                    ];
                }
            }
        }

        return new JsonResponse($result);
    }
}
