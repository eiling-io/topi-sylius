<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Catalog;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;

class CatalogProduct
{
    public string $title;

    public ?string $subtitle = null;

    public string $description;

    /**
     * @var string[]
     */
    public array $descriptionLines = [];

    public bool $isActive;

    public ?MoneyAmount $price = null;

    public ?string $manufacturer = null;

    /**
     * @var ProductIdentifier[]
     */
    public array $productStandardIdentifiers = [];

    /**
     * @var Category[]
     */
    public array $sellerCategories = [];

    /**
     * @var ProductReference[]
     */
    public array $sellerProductReferences = [];

    public ?string $shopProductDescriptionUrl = null;

    /**
     * @var ExtraProductDetails[]
     */
    public array $extraDetails = [];
}
