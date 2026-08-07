<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;

class PricingOverview
{
    /**
     * @var BreakdownLine[]
     */
    public array $breakdown = [];

    public MoneyAmount $insteadOfAmount;

    public MoneyAmount $shippingAmount;

    public MoneyAmount $totalAmount;
}
