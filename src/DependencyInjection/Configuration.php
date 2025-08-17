<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Roadrunner\Integration\Symfony\Http\InstalledVersions;
use Sentry\SentryBundle\SentryBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface as BundleConfiguration;

/**
 * @phpstan-type RawConfiguration array{
 *    logger: non-empty-string,
 *    useSentryIntegration: bool,
 *    middlewares: array<non-empty-string>,
 *    useDoctrineIntegration: array<non-empty-string>,
 *    useLoggingDoctrineOpenTransaction: array<non-empty-string>,
 *    useTrackingSentryDoctrineOpenTransaction: array<non-empty-string>
 * }
 */
final class Configuration implements BundleConfiguration
{
    /**
     * @param array<non-empty-string> $connections
     * @param array<non-empty-string> $entityManagers
     */
    public function __construct(
        private readonly array $connections,
        private readonly array $entityManagers,
    ) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('http');

        $sentryValidator = static function (): bool {
            if (!InstalledVersions::willBeAvailable('sentry/sentry-symfony', SentryBundle::class, [])) {
                return true;
            }

            return false;
        };

        $doctrineIntegrationValidator = function (array $values): bool {
            if (!InstalledVersions::willBeAvailable('doctrine/doctrine-bundle', EntityManager::class, [])) {
                throw new InvalidArgumentException('Install dependencies `composer req orm`');
            }

            if (!(count($values) == count(array_unique($values)))) {
                throw new InvalidArgumentException('Should not be repeated entity-manager');
            }

            if ($values == []) {
                throw new InvalidArgumentException('Please set entity-manager name.');
            }

            $notFoundEntityManages = [];

            if ($this->entityManagers == []) {
                return false;
            }

            foreach ($values as $value) {
                if (!in_array($value, $this->entityManagers, true)) {
                    $notFoundEntityManages[] = $value;
                }
            }

            if ($notFoundEntityManages != []) {
                throw new InvalidArgumentException(sprintf("Not found entity managers: %s", implode(', ', $notFoundEntityManages)));
            }

            return false;
        };

        $loggingDoctrineOpenTransactionValidator = function (array $values): bool {
            if (!InstalledVersions::willBeAvailable('doctrine/doctrine-bundle', EntityManager::class, [])) {
                throw new InvalidArgumentException('Install dependencies `composer req orm`');
            }

            if (!InstalledVersions::willBeAvailable('symfony/monolog-bundle', MonologBundle::class, [])) {
                throw new InvalidArgumentException('Install dependencies `composer req log`');
            }

            if (!(count($values) == count(array_unique($values)))) {
                throw new InvalidArgumentException('Should not be repeated connection');
            }

            if ($values == []) {
                throw new InvalidArgumentException('Please set entity-manager name.');
            }

            $notFoundConnections = [];

            if ($this->connections == []) {
                return false;
            }

            foreach ($values as $value) {
                if (!in_array($value, $this->connections, true)) {
                    $notFoundConnections[] = $value;
                }
            }

            if ($notFoundConnections != []) {
                throw new InvalidArgumentException(sprintf("Not found entity managers: %s", implode(', ', $notFoundConnections)));
            }

            return false;
        };

        $trackingSentryDoctrineOpenTransactionValidator = function (array $values) use ($sentryValidator, $loggingDoctrineOpenTransactionValidator): bool {
            if ($sentryValidator()) {
                throw new InvalidArgumentException('Install dependencies `composer req sentry`');
            }

            $loggingDoctrineOpenTransactionValidator($values);

            return false;
        };


        //@formatter:off
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('logger')
                    ->defaultValue('monolog.logger.roadrunner.http')
                    ->info('Logger for http worker, accepts serviceId')
                ->end()

                ->booleanNode('useSentryIntegration')
                    ->defaultFalse()
                    ->info('Connect Sentry integration')
                ->end()

                ->arrayNode('middlewares')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->validate()
                        ->ifTrue(static function (array $values): bool {
                            return !(count($values) == count(array_unique($values)));
                        })
                        ->thenInvalid('Should not be repeated middlewares')
                    ->end()
                    ->info('List of middleware')
                ->end()

                ->arrayNode('useDoctrineIntegration')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->validate()
                        ->ifTrue($doctrineIntegrationValidator)
                        ->thenInvalid('Should not be repeated entity-manager')
                    ->end()
                    ->info('Connects Doctrine integration. You need to pass a list to entity-manager.')
                ->end()

                ->arrayNode('useLoggingDoctrineOpenTransaction')
                    ->validate()
                        ->ifTrue($loggingDoctrineOpenTransactionValidator)
                        ->thenInvalid('Should not be repeated connection')
                    ->end()
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Attaches an middleware that reports uncompleted transactions in the logs (monolog) after the action completes. You need to pass a list to connection(dbal).')
                ->end()

                ->arrayNode('useTrackingSentryDoctrineOpenTransaction')
                    ->validate()
                        ->ifTrue($trackingSentryDoctrineOpenTransactionValidator)
                        ->thenInvalid('Should not be repeated connection')
                    ->end()
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Attaches an middleware that reports outstanding transactions in sentry after the activity has completed. You need to pass a list to connection(dbal).')
                ->end()
            ->end()
        ;


        return $treeBuilder;
    }
}
