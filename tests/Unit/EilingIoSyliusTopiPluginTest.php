<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit;

use EilingIo\SyliusTopiPlugin\EilingIoSyliusTopiPlugin;
use PHPUnit\Framework\TestCase;

final class EilingIoSyliusTopiPluginTest extends TestCase
{
    public function testGetPathReturnsThePackageRoot(): void
    {
        $plugin = new EilingIoSyliusTopiPlugin();

        self::assertSame(\dirname(__DIR__, 2), $plugin->getPath());
        self::assertFileExists($plugin->getPath() . '/composer.json');
    }
}
