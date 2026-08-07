<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Common;

class MoneyAmount
{
    public string $currency;

    public int $gross;

    public int $net;

    public function getNetFormatted(): float
    {
        return $this->net / 100;
    }

    public function getGrossFormatted(): float
    {
        return $this->gross / 100;
    }
}
