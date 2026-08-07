<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

class PostalAddress
{
    public string $city;

    public string $countryCode;

    public string $line1;

    public ?string $line2 = null;

    public string $postalCode;

    public ?string $region = null;

    /**
     * Only present on an order's `shipping_address` (not on `company.billing_address`)
     * — the name Topi's hosted checkout collected for delivery, distinct from the
     * customer's own full name.
     */
    public ?string $recipientName = null;
}
