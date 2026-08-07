<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;

class PricingRequest
{
    public MoneyAmountWithOptionalTax $price;

    public ProductReference $sellerProductReference;
}
