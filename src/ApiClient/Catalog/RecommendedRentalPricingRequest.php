<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;

class RecommendedRentalPricingRequest
{
    public ProductReference $sellerProductReference;

    public ?MoneyAmountWithOptionalTax $price = null;

    public ?MoneyAmountWithOptionalTax $baseRentalPrice = null;
}
