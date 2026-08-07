<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;

class BreakdownLine
{
    public MoneyAmount $amount;

    public string $title;

    public ?string $tooltip = null;
}
