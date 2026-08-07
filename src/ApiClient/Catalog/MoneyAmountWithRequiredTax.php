<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;

class MoneyAmountWithRequiredTax extends MoneyAmount
{
    public int $taxRate;
}
