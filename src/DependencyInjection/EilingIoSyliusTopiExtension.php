<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Named EilingIoSyliusTopiExtension (not "...TopiPluginExtension") because
 * SyliusPluginTrait::getContainerExtension() derives the expected class name by
 * stripping the trailing "Plugin" from the bundle's short name first. The plugin
 * previously shipped this class as EilingIoSyliusTopiExtension, which
 * Symfony's naming convention never actually matched — meaning it was silently never
 * discovered as this bundle's container extension.
 */
final class EilingIoSyliusTopiExtension extends AbstractResourceExtension implements
    PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /** @psalm-suppress UnusedVariable */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@EilingIoSyliusTopiPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
