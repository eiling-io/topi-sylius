<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

class CreateOfferData extends BaseOffer
{
    public string $exitRedirect;

    public string $expiresAt;

    public string $successRedirect;
}
