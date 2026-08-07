<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\BaseClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\CommonMapper;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReferenceCollection;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductSummary;

use JsonException;

use function array_key_exists;
use function array_map;

use const JSON_THROW_ON_ERROR;

class CatalogClient extends BaseClient
{
    private GuzzleClient $client;

    private LoggerInterface $logger;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $responseCache = [];

    public function __construct(
        GuzzleClient $client,
        LoggerInterface $logger,
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $options
     * @throws JsonException
     * @return ProductSummary[]
     */
    public function checkSupported(ProductReferenceCollection $productReferences, array $options = []): array
    {
        $jsonData = [
            'seller_product_references' => array_map(
                CommonMapper::productReferenceToArray(...),
                $productReferences->getProductReferences()
            ),
        ];

        $start = microtime(true);
        $requestOptions = array_merge([
            'json' => $jsonData,
        ], $options);

        if (!isset($this->responseCache[__METHOD__])) {
            $this->responseCache[__METHOD__] = [];
        }

        $cacheKey = md5(serialize($requestOptions));
        $dontUseCache = isset($options['cache']) && $options['cache'] === false;
        if (array_key_exists($cacheKey, $this->responseCache[__METHOD__]) && !$dontUseCache) {
            return $this->responseCache[__METHOD__][$cacheKey];
        }

        $response = $this->client->post('catalog/check-supported', $this->preProcessOptions($requestOptions));

        $responseData = json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $result = [];
        foreach ($responseData['products'] as $productSummaryData) {
            $result[] = CommonMapper::productSummaryFromArray($productSummaryData);
        }

        $this->responseCache[__METHOD__][$cacheKey] = $result;

        return $result;
    }

    /**
     * @param CatalogProduct[] $products
     * @param array<string, mixed> $options
     */
    public function importCatalog(array $products, array $options = []): void
    {
        $jsonData = [
            'products' => array_map(CatalogMapper::catalogProductToArray(...), $products),
        ];

        $start = microtime(true);
        $response = $this->client->post('catalog/import', $this->preProcessOptions(array_merge([
            'json' => $jsonData,
        ], $options)));

        $this->logger->debug(
            'topi catalog import took: ' . (microtime(true) - $start) . 's, status: ' . $response->getStatusCode()
        );
    }

    /**
     * @param RecommendedRentalPricingRequest[] $pricingRequests
     * @param array<string, mixed> $options
     * @throws JsonException
     * @return RecommendedRentalPricingDetails[]
     */
    public function listRecommendedRentalPrices(array $pricingRequests, array $options = []): array
    {
        try {
            $response = $this->client->post(
                'catalog/list-recommended-rental-prices',
                $this->preProcessOptions(
                    array_merge(
                        [
                            'json' => [
                                'pricing_requests' => array_map(
                                    CatalogMapper::recommendedRentalPricingRequestToArray(...),
                                    $pricingRequests
                                ),
                            ],
                        ],
                        $options
                    )
                )
            );
        } catch (RequestException $e) {
            if (!$e->hasResponse()) {
                throw $e;
            }

            $this->logger->debug(
                'topi listRecommendedRentalPrices error: ' . $e->getMessage() . '; Response: ' . $e->getResponse(
                )->getBody()
            );

            throw $e;
        }

        $responseData = json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $result = [];
        foreach ($responseData as $itemData) {
            $result[] = CatalogMapper::recommendedRentalPricingDetailsFromArray($itemData);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function calculatePricing(PricingRequest $pricingRequest, array $options = []): CalculatePricingResponse
    {
        $jsonData = [
            'pricing_request' => CatalogMapper::pricingRequestToArray($pricingRequest),
        ];

        try {
            $response = $this->client->post(
                'catalog/pricing',
                $this->preProcessOptions(array_merge([
                    'json' => $jsonData,
                ], $options))
            );
        } catch (RequestException $e) {
            if (!$e->hasResponse()) {
                throw $e;
            }

            if ($e->getResponse()->getStatusCode() !== 404) {
                $this->logger->debug('Error: ' . $e->getMessage() . '; Response: ' . $e->getResponse()->getBody());
            }

            throw $e;
        }

        $responseData = json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return CatalogMapper::calculatePricingResponseFromArray($responseData);
    }
}
