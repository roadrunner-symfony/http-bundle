<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @phpstan-import-type RawConfiguration from Configuration
 */
final class HttpExtension extends Extension
{
    public const CONFIG_NAME = 'roadrunner.http.config';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('service.php');

        $entityManagers = [];
        $connections    = [];

        if ($container->hasParameter('doctrine.entity_managers')) {
            /** @var array<non-empty-string, non-empty-string> $rawEntityManagers */
            $rawEntityManagers = $container->getParameter('doctrine.entity_managers');

            $entityManagers = array_keys($rawEntityManagers);
        }

        if ($container->hasParameter('doctrine.connections')) {
            /** @var array<non-empty-string, non-empty-string> $rawConnections */
            $rawConnections = $container->getParameter('doctrine.connections');

            $connections = array_keys($rawConnections);
        }

        $configuration = new Configuration($connections, $entityManagers);
        /** @var RawConfiguration $rawConfiguration */
        $rawConfiguration = $this->processConfiguration($configuration, $configs);

        $container->setParameter(self::CONFIG_NAME, $rawConfiguration);
    }


    /**
     * @param array<string, mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        $entityManagers = [];
        $connections    = [];

        if ($container->hasParameter('doctrine.entity_managers')) {
            /** @var array<non-empty-string, non-empty-string> $rawEntityManagers */
            $rawEntityManagers = $container->getParameter('doctrine.entity_managers');

            $entityManagers = array_keys($rawEntityManagers);
        }

        if ($container->hasParameter('doctrine.connections')) {
            /** @var array<non-empty-string, non-empty-string> $rawConnections */
            $rawConnections = $container->getParameter('doctrine.connections');

            $connections = array_keys($rawConnections);
        }


        return new Configuration($connections, $entityManagers);
    }
}
