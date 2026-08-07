<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\BaseClient;

use const JSON_THROW_ON_ERROR;

class OfferClient extends BaseClient
{
    private GuzzleClient $client;

    private LoggerInterface $logger;

    public function __construct(
        GuzzleClient $client,
        LoggerInterface $logger,
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createOffer(CreateOfferData $offer, array $options = []): CreatedOffer
    {
        $start = microtime(true);
        $response = $this->client->post('offers', $this->preProcessOptions(array_merge([
            'json' => OfferMapper::createOfferToArray($offer),
        ], $options)));

        $responseData = (string) $response->getBody();

        $timeElapsedSecs = microtime(true) - $start;
        $this->logger->debug('topi API took: ' . $timeElapsedSecs . 's');
        $this->logger->debug('Response: ' . $response->getStatusCode() . ' Data: ' . $responseData);

        return OfferMapper::createdOfferFromArray(
            json_decode(
                $responseData,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function validateOffer(CreateOfferData $offer, array $options = []): PricingOverview
    {
        $start = microtime(true);

        try {
            $response = $this->client->post('offers/validate', $this->preProcessOptions(array_merge([
                'json' => OfferMapper::createOfferToArray($offer),
            ], $options)));
        } catch (RequestException $exception) {
            $this->logger->debug('Response: ' . $exception->getResponse()->getStatusCode() . ' Data: ' . $exception->getResponse()->getBody());

            throw $exception;
        }

        $timeElapsedSecs = microtime(true) - $start;
        $this->logger->debug('topi API took: ' . $timeElapsedSecs . 's');
        $this->logger->debug('Response: ' . $response->getStatusCode() . ' Data: ' . $response->getBody());

        $responseData = (string) $response->getBody();

        return OfferMapper::pricingOverviewFromArray(
            json_decode(
                $responseData,
                true,
                512,
                JSON_THROW_ON_ERROR
            )['pricing_overview']
        );
    }
}
