<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient;

use Psr\Log\LoggerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Factory\GuzzleClientFactory;
use EilingIo\SyliusTopiPlugin\ApiClient\Offer\OfferClient;
use EilingIo\SyliusTopiPlugin\ApiClient\Order\OrderClient;
use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethodClient;

class Client
{
    private GuzzleClientFactory $clientFactory;

    /**
     * @var CatalogClient[]
     */
    private array $catalogClients = [];

    /**
     * @var OfferClient[]
     */
    private array $offerClients = [];

    /**
     * @var ShippingMethodClient[]
     */
    private array $shippingMethodClients = [];

    /**
     * @var OrderClient[]
     */
    private array $orderClients = [];

    private LoggerInterface $logger;

    public function __construct(
        GuzzleClientFactory $clientFactory,
        LoggerInterface $logger,
    ) {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
    }

    public function catalog(?string $clientId = null, ?string $clientSecret = null): CatalogClient
    {
        $cacheKey = $this->getCacheKey($clientId, $clientSecret);
        if (!isset($this->catalogClients[$cacheKey])) {
            $this->catalogClients[$cacheKey] = new CatalogClient(
                $this->clientFactory->make($clientId, $clientSecret),
                $this->logger
            );
        }

        return $this->catalogClients[$cacheKey];
    }

    public function offer(?string $clientId = null, ?string $clientSecret = null): OfferClient
    {
        $cacheKey = $this->getCacheKey($clientId, $clientSecret);
        if (!isset($this->offerClients[$cacheKey])) {
            $this->offerClients[$cacheKey] = new OfferClient(
                $this->clientFactory->make($clientId, $clientSecret),
                $this->logger
            );
        }

        return $this->offerClients[$cacheKey];
    }

    public function shippingMethod(?string $clientId = null, ?string $clientSecret = null): ShippingMethodClient
    {
        $cacheKey = $this->getCacheKey($clientId, $clientSecret);
        if (!isset($this->shippingMethodClients[$cacheKey])) {
            $this->shippingMethodClients[$cacheKey] = new ShippingMethodClient(
                $this->clientFactory->make($clientId, $clientSecret),
                $this->logger
            );
        }

        return $this->shippingMethodClients[$cacheKey];
    }

    public function order(?string $clientId = null, ?string $clientSecret = null): OrderClient
    {
        $cacheKey = $this->getCacheKey($clientId, $clientSecret);
        if (!isset($this->orderClients[$cacheKey])) {
            $this->orderClients[$cacheKey] = new OrderClient(
                $this->clientFactory->make($clientId, $clientSecret),
                $this->logger
            );
        }

        return $this->orderClients[$cacheKey];
    }

    private function getCacheKey(?string $clientId = null, ?string $clientSecret = null): string
    {
        return ($clientId ?? 'default') . ':' . ($clientSecret ?? 'default');
    }
}
