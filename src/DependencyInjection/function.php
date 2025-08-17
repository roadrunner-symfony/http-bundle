<?php

/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @param class-string|null                  $class
 * @param array<int|non-empty-string,mixed>  $arguments
 */
function definition(?string $class = null, array $arguments = []): Definition
{
    return new Definition($class, $arguments);
}

/**
 * @internal
 *
 * @param non-empty-string $id
 */
function reference(string $id, int $invalidBehavior = Container::EXCEPTION_ON_INVALID_REFERENCE): Reference
{
    return new Reference($id, $invalidBehavior);
}

/**
 * @internal
 */
function referenceLogger(): Reference
{
    return reference('monolog.logger.roadrunner.http', Container::IGNORE_ON_INVALID_REFERENCE);
}


/**
 * @internal
 *
 * @param non-empty-string $entityManager
 *
 * @return non-empty-string
 */
function doctrinePingMiddlewareId(string $entityManager): string
{
    return sprintf('roadrunner.http.doctrine_ping_connection_%s.middleware', $entityManager);
}


/**
 * @internal
 *
 * @return non-empty-string
 */
function doctrineClearEntityManagerMiddlewareId(): string
{
    return 'roadrunner.http.doctrine_clear_entity_manager.finalizer';
}


/**
 * @internal
 *
 * @param non-empty-string $connection
 *
 * @return non-empty-string
 */
function trackingSentryDoctrineOpenTransactionMiddlewareId(string $connection): string
{
    return sprintf('roadrunner.http.tracking_sentry_open_transaction_%s.middleware', $connection);
}


/**
 * @internal
 *
 * @param non-empty-string $connection
 *
 * @return non-empty-string
 */
function loggingDoctrineOpenTransactionMiddlewareId(string $connection): string
{
    return sprintf('roadrunner.http.logging_open_transaction_%s.middleware', $connection);
}
