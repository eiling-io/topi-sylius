<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

class RentContractTerm
{
    public int $duration;

    public ?string $id = null;

    public MoneyAmountWithRequiredTax $monthlyAmount;
}
