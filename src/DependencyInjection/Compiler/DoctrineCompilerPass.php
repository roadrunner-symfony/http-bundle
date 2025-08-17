<?php

/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManager;
use Roadrunner\Integration\Symfony\Http\Bridge\Doctrine\ConnectionPingMiddleware;
use Roadrunner\Integration\Symfony\Http\Bridge\Doctrine\PsrLoggingDoctrineOpenTransactionMiddleware;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Configuration;

use function Roadrunner\Integration\Symfony\Http\DependencyInjection\definition;
use function Roadrunner\Integration\Symfony\Http\DependencyInjection\doctrinePingMiddlewareId;

use Roadrunner\Integration\Symfony\Http\DependencyInjection\HttpExtension;

use function Roadrunner\Integration\Symfony\Http\DependencyInjection\loggingDoctrineOpenTransactionMiddlewareId;
use function Roadrunner\Integration\Symfony\Http\DependencyInjection\referenceLogger;

use Roadrunner\Integration\Symfony\Http\InstalledVersions;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type RawConfiguration from Configuration
 */
final class DoctrineCompilerPass implements CompilerPass
{
    public function process(ContainerBuilder $container): void
    {
        if (!InstalledVersions::willBeAvailable('doctrine/doctrine-bundle', EntityManager::class, [])) {
            return;
        }

        if (!$container->hasParameter('doctrine.entity_managers')) {
            return;
        }


        /** @var array<non-empty-string, non-empty-string> $entityManagers */
        $entityManagers = $container->getParameter('doctrine.entity_managers');
        /** @var RawConfiguration $config */
        $config          = $container->getParameter('roadrunner.http.config');
        $pingMiddlewares = [];

        foreach ($entityManagers as $entityManager => $id) {
            if (!in_array($entityManager, $config['useDoctrineIntegration'])) {
                continue;
            }

            $middlewareId      = doctrinePingMiddlewareId($entityManager);
            $pingMiddlewares[] = $middlewareId;

            $container->register($middlewareId, ConnectionPingMiddleware::class)
                ->setArguments([
                    definition(ConnectionPingMiddleware::class)
                        ->setArguments([
                            new Reference('doctrine'),
                            $entityManager,
                            referenceLogger(),
                        ]),
                ])
            ;
        }

        $config['middlewares'] = [...$config['middlewares'], ...$pingMiddlewares];

        $container->setParameter(HttpExtension::CONFIG_NAME, $config);


        if (!InstalledVersions::willBeAvailable('symfony/monolog-bundle', MonologBundle::class, [])) {
            return;
        }

        if (!$container->hasParameter('doctrine.connections')) {
            return;
        }

        /** @var array<non-empty-string, non-empty-string> $connections */
        $connections                = $container->getParameter('doctrine.connections');
        $loggingUnclosedMiddlewares = [];


        foreach ($connections as $connectionName => $connectionId) {
            if (!in_array($connectionName, $config['useLoggingDoctrineOpenTransaction'])) {
                continue;
            }

            $container->register(loggingDoctrineOpenTransactionMiddlewareId($connectionName), PsrLoggingDoctrineOpenTransactionMiddleware::class)
                ->setArguments([
                    referenceLogger(),
                    new Reference($connectionId),
                ])
            ;
        }


        $config['middlewares'] = [...$config['middlewares'], ...$loggingUnclosedMiddlewares];
        $container->setParameter(HttpExtension::CONFIG_NAME, $config);
    }
}
