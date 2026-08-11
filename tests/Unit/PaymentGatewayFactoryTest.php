<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit;

use EilingIo\SyliusTopiPlugin\PaymentGatewayFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PaymentGatewayFactoryTest extends TestCase
{
    public function testPopulateConfigSetsTheFactoryNameAndTitleDefaults(): void
    {
        $factory = new PaymentGatewayFactory();
        $config = new ArrayObject();

        // populateConfig() is protected — Payum's own GatewayFactory only calls it
        // internally as part of create()/createConfig(), so reflection is simpler
        // here than wiring up a full gateway just to exercise this one method.
        $method = new ReflectionMethod(PaymentGatewayFactory::class, 'populateConfig');
        $method->invoke($factory, $config);

        self::assertSame('topi_payment', $config['payum.factory_name']);
        self::assertSame('Topi Payment', $config['payum.factory_title']);
    }
}
