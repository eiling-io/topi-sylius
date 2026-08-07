<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Common;

class ContractTermsSummary
{
    public ?bool $canPayNow = null;

    public ?bool $canRent = null;

    public ProductRentContract $rent;
}
