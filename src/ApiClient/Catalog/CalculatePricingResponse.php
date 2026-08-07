<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

class CalculatePricingResponse
{
    /**
     * @var string[]
     */
    public array $availableContractTypes = [];

    public bool $isSupported;

    public ?PayNowContractTerm $payNow = null;

    /**
     * @var RentContractTerm[]
     */
    public array $rent = [];

    public string $summary;
}
