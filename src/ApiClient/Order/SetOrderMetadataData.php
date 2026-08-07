<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Order;

class SetOrderMetadataData
{
    public string $orderId;

    /**
     * @var array<string, string>|null
     */
    public ?array $metadata = null;
}
