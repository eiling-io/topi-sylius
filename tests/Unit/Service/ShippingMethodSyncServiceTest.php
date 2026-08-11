<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Service;

use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\ShippingMethod\ShippingMethodClient;
use EilingIo\SyliusTopiPlugin\Service\ShippingMethodSyncService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Repository\ShippingMethodRepositoryInterface;

final class ShippingMethodSyncServiceTest extends TestCase
{
    public function testSyncAllSkipsDisabledMethods(): void
    {
        $method = $this->method(enabled: false, name: 'UPS', code: 'ups');

        $repository = $this->createMock(ShippingMethodRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$method]);

        $topiClient = $this->createMock(Client::class);
        $topiClient->expects($this->never())->method('shippingMethod');

        $service = new ShippingMethodSyncService($topiClient, $repository);
        $result = $service->syncAll();

        self::assertSame(['synced' => 0, 'skipped' => 1], $result);
    }

    public function testSyncAllSkipsMethodsWithoutAName(): void
    {
        $method = $this->method(enabled: true, name: null, code: 'ups');

        $repository = $this->createMock(ShippingMethodRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$method]);

        $topiClient = $this->createMock(Client::class);
        $topiClient->expects($this->never())->method('shippingMethod');

        $service = new ShippingMethodSyncService($topiClient, $repository);
        $result = $service->syncAll();

        self::assertSame(['synced' => 0, 'skipped' => 1], $result);
    }

    public function testSyncAllCreatesEnabledMethodsAndReportsProgress(): void
    {
        $method = $this->method(enabled: true, name: 'UPS', code: 'ups');

        $repository = $this->createMock(ShippingMethodRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$method]);

        $shippingMethodClient = $this->createMock(ShippingMethodClient::class);
        $shippingMethodClient->expects($this->once())->method('create')->willReturnCallback(
            function ($topiMethod) {
                self::assertSame('UPS', $topiMethod->name);
                self::assertSame('ups', $topiMethod->sellerShippingMethodReference);
            },
        );

        $topiClient = $this->createMock(Client::class);
        $topiClient->method('shippingMethod')->willReturn($shippingMethodClient);

        $progressCalls = [];
        $service = new ShippingMethodSyncService($topiClient, $repository);
        $result = $service->syncAll(function (int $synced, int $skipped) use (&$progressCalls) {
            $progressCalls[] = [$synced, $skipped];
        });

        self::assertSame(['synced' => 1, 'skipped' => 0], $result);
        self::assertSame([[1, 0]], $progressCalls);
    }

    public function testSyncAllWrapsAFailedCreateInARuntimeException(): void
    {
        $method = $this->method(enabled: true, name: 'UPS', code: 'ups');

        $repository = $this->createMock(ShippingMethodRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$method]);

        $shippingMethodClient = $this->createMock(ShippingMethodClient::class);
        $shippingMethodClient->method('create')->willThrowException(new \RuntimeException('API down'));

        $topiClient = $this->createMock(Client::class);
        $topiClient->method('shippingMethod')->willReturn($shippingMethodClient);

        $service = new ShippingMethodSyncService($topiClient, $repository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('topi shipping method sync failed for code ups');

        $service->syncAll();
    }

    /**
     * @return ShippingMethodInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function method(bool $enabled, ?string $name, string $code): ShippingMethodInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('isEnabled')->willReturn($enabled);
        $method->method('getName')->willReturn($name);
        $method->method('getCode')->willReturn($code);

        return $method;
    }
}
