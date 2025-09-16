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
use Roadrunner\Integration\Symfony\Http\Bridge\Doctrine\SentryDoctrineOpenTransactionMiddleware;
use Roadrunner\Integration\Symfony\Http\Bridge\Sentry\SentryScopeMiddleware;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Configuration;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\RoadRunnerHttpExtension;

use function Roadrunner\Integration\Symfony\Http\DependencyInjection\trackingSentryDoctrineOpenTransactionMiddlewareId;

use Roadrunner\Integration\Symfony\Http\InstalledVersions;
use Sentry\SentryBundle\SentryBundle;
use Sentry\State\HubInterface as Hub;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type RawConfiguration from Configuration
 */
final class SentryCompilerPass implements CompilerPass
{
    public function process(ContainerBuilder $container): void
    {
        if (!InstalledVersions::willBeAvailable('sentry/sentry-symfony', SentryBundle::class, [])) {
            return;
        }

        /** @var RawConfiguration $config */
        $config = $container->getParameter(RoadRunnerHttpExtension::CONFIG_NAME);

        if ($config['useSentryIntegration']) {
            $container->register('roadrunner.http.sentry_scope.middleware', SentryScopeMiddleware::class)
                ->setArguments([
                    new Reference(Hub::class),
                ])
            ;

            $config['middlewares'][] = 'roadrunner.http.sentry_scope.middleware';
        }

        $container->setParameter(RoadRunnerHttpExtension::CONFIG_NAME, $config);


        if (!InstalledVersions::willBeAvailable('doctrine/doctrine-bundle', EntityManager::class, [])) {
            return;
        }

        if (!$container->hasParameter('doctrine.connections')) {
            return;
        }

        /** @var array<non-empty-string, non-empty-string> $connections */
        $connections                 = $container->getParameter('doctrine.connections');
        $trackingUnclosedMiddlewares = [];

        foreach ($connections as $connectionName => $connectionId) {
            if (!in_array($connectionName, $config['useTrackingSentryDoctrineOpenTransaction'])) {
                continue;
            }

            $middlewareId                  = trackingSentryDoctrineOpenTransactionMiddlewareId($connectionName);
            $trackingUnclosedMiddlewares[] = $middlewareId;

            $container->register($middlewareId, SentryDoctrineOpenTransactionMiddleware::class)
                ->setArguments([
                    new Reference(Hub::class),
                    new Reference($connectionId),
                ])
            ;
        }

        $config['middlewares'] = [...$config['middlewares'], ...$trackingUnclosedMiddlewares];
        $container->setParameter(RoadRunnerHttpExtension::CONFIG_NAME, $config);
    }
}
