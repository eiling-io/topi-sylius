<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethod as TopiShippingMethod;
use RuntimeException;
use Sylius\Component\Shipping\Repository\ShippingMethodRepositoryInterface;
use Throwable;

use function sprintf;

class ShippingMethodSyncService
{
    private const LOCALE = 'de_DE';

    public function __construct(
        private readonly Client $topiClient,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
    ) {
    }

    /**
     * @return array{synced: int, skipped: int}
     */
    public function syncAll(?callable $progressCallback = null): array
    {
        $shippingMethods = $this->shippingMethodRepository->findAll();

        $synced = 0;
        $skipped = 0;

        foreach ($shippingMethods as $method) {
            if (!$method->isEnabled()) {
                $skipped++;

                continue;
            }

            $method->setCurrentLocale(self::LOCALE);
            $name = $method->getName();

            if ($name === null || $name === '') {
                $skipped++;

                continue;
            }

            $topiMethod = new TopiShippingMethod();
            $topiMethod->name = $name;
            $topiMethod->sellerShippingMethodReference = (string) $method->getCode();

            try {
                $this->topiClient->shippingMethod()->create($topiMethod);
                $synced++;
            } catch (Throwable $e) {
                throw new RuntimeException(
                    sprintf('topi shipping method sync failed for code %s', (string) $method->getCode()),
                    0,
                    $e,
                );
            }

            if ($progressCallback !== null) {
                $progressCallback($synced, $skipped);
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }
}
