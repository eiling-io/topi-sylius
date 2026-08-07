<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin;

enum PaymentStatus: string
{
    case OFFER_CREATED = 'offer_created';
    case OFFER_PENDING_REVIEW = 'offer_pending_review';
    case OFFER_ACCEPTED = 'offer_accepted';
    case OFFER_DECLINED = 'offer_declined';
    case OFFER_EXPIRED = 'offer_expired';
    case OFFER_VOIDED = 'offer_voided';
    case ORDER_CREATED = 'order_created';
    case ORDER_CONFIRMED = 'order_confirmed';
    case ORDER_COMPLETED = 'order_completed';
    case ORDER_CANCELED = 'order_canceled';
    case ORDER_REJECTED = 'order_rejected';

    public function settlement(): PaymentSettlement
    {
        return match ($this) {
            self::OFFER_CREATED, self::OFFER_PENDING_REVIEW, self::OFFER_ACCEPTED => PaymentSettlement::PENDING,
            self::ORDER_CREATED, self::ORDER_CONFIRMED, self::ORDER_COMPLETED => PaymentSettlement::CAPTURED,
            self::OFFER_DECLINED, self::ORDER_REJECTED => PaymentSettlement::FAILED,
            self::OFFER_EXPIRED, self::OFFER_VOIDED, self::ORDER_CANCELED => PaymentSettlement::CANCELED,
        };
    }
}
