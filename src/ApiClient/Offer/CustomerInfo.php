<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

class CustomerInfo
{
    public CompanyInfo $company;

    public ?string $customerGroup = null;

    public string $email;

    public ?string $fullName = null;
}
