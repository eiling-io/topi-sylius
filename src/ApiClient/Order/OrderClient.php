<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Order;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\LoggerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\BaseClient;

use function sprintf;

use const JSON_THROW_ON_ERROR;

class OrderClient extends BaseClient
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
    public function getOrder(string $orderId, array $options = []): Order
    {
        $response = $this->client->get(sprintf('orders/%s', $orderId), $this->preProcessOptions($options));

        return OrderMapper::orderFromArray(
            json_decode(
                (string) $response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOrderMetadata(SetOrderMetadataData $data, array $options = []): Order
    {
        $start = microtime(true);
        $response = $this->client->patch(sprintf('orders/%s', $data->orderId), $this->preProcessOptions(array_merge([
            'json' => [
                'metadata' => $data->metadata,
            ],
        ], $options)));

        $responseData = (string) $response->getBody();

        $timeElapsedSecs = microtime(true) - $start;
        $this->logger->debug('topi API took: ' . $timeElapsedSecs . 's');
        $this->logger->debug('Response: ' . $response->getStatusCode() . ' Data: ' . $responseData);

        return OrderMapper::orderFromArray(
            json_decode(
                $responseData,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }
}
