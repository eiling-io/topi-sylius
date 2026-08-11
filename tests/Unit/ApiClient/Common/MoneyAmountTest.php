<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient\Common;

use EilingIo\SyliusTopiPlugin\ApiClient\Common\MoneyAmount;
use PHPUnit\Framework\TestCase;

final class MoneyAmountTest extends TestCase
{
    public function testGetNetFormatted(): void
    {
        $amount = new MoneyAmount();
        $amount->net = 1234;

        self::assertEqualsWithDelta(12.34, $amount->getNetFormatted(), 0.001);
    }

    public function testGetGrossFormatted(): void
    {
        $amount = new MoneyAmount();
        $amount->gross = 4068;

        self::assertEqualsWithDelta(40.68, $amount->getGrossFormatted(), 0.001);
    }
}
