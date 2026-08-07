<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\BaseClient;

use Generator;
use JsonException;

use const JSON_THROW_ON_ERROR;

class ShippingMethodClient extends BaseClient
{
    public function __construct(
        private readonly GuzzleClient $client,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     *
     * @throws JsonException
     * @return Generator<array{seller_shipping_method_reference: string, supported: bool}>
     */
    public function list(int $page = 0): Generator
    {
        $response = $this->client->get('shipping-method', [
            'query' => [
                'page' => $page,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        yield from $data['data'];

        if ($data['pagination']['has_more']) {
            yield from $this->list($data['pagination']['page'] + 1);
        }
    }

    public function create(ShippingMethod $shippingMethod): void
    {
        try {
            $this->client->post('shipping-method/method', [
                'json' => ShippingMethodMapper::toArray($shippingMethod),
            ]);

            $this->logger->debug('topi shipping method created: ' . $shippingMethod->sellerShippingMethodReference);
        } catch (ClientException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 422) {
                $this->logger->debug('topi shipping method already exists: ' . $shippingMethod->sellerShippingMethodReference);

                return;
            }

            throw $e;
        }
    }
}
