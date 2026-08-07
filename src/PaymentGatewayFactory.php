<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class PaymentGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'topi_payment',
            'payum.factory_title' => 'Topi Payment',
        ]);
    }
}
