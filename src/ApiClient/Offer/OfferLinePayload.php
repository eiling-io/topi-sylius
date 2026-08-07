<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;

class OfferLinePayload
{
    public MoneyAmount $price;

    public int $quantity;

    public ProductReference $sellerProductReference;

    public string $title;

    public ?string $subtitle = null;
}
