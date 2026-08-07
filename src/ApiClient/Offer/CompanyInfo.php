<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

class CompanyInfo
{
    public PostalAddress $billingAddress;

    public string $name;

    public ?string $taxNumber = null;

    public ?string $vatNumber = null;
}
