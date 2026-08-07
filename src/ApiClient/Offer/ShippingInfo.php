<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;

class ShippingInfo
{
    public MoneyAmount $price;

    public string $sellerShippingReference;
}
