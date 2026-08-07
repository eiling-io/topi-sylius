<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

class PayNowContractTerm
{
    public ?string $id = null;

    public MoneyAmountWithRequiredTax $amount;
}
