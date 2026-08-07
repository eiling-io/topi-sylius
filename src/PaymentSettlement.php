<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin;

enum PaymentSettlement
{
    case PENDING;
    case CAPTURED;
    case FAILED;
    case CANCELED;
}
