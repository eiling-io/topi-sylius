<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;

class RecommendedRentalPricingDetails
{
    public ProductReference $sellerProductReference;

    public bool $hasRentalTerms;

    public ?string $currency = null;

    public ?int $monthlyRentalAmount = null;

    public ?RentContractTerm $monthlyRentalTerms = null;

    public ?string $summary = null;
}
