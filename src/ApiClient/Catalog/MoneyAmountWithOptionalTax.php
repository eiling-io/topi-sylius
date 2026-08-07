<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;

class MoneyAmountWithOptionalTax extends MoneyAmount
{
    public ?int $taxRate = null;
}
