<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Offer;

use DateTime;

class CreatedOffer extends BaseOffer
{
    public string $checkoutRedirectUrl = '';

    public DateTime $createdAt;

    public string $id;

    /**
     * @var 'created'|'voided'|'accepted'|'expired'|'rejected'|'pending_review'|'declined' $status
     */
    public string $status;
}
