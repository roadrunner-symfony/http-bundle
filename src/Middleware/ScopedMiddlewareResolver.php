<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware;

use Closure;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Roadrunner\Integration\Symfony\Http\Middleware\Attribute\UseMiddleware;

/**
 * @phpstan-type Binding array{id: string, scope: 'controller'|'action'|'route', priority: int}
 */
final class ScopedMiddlewareResolver
{
    private const SPECIFICITY = ['controller' => 0, 'action' => 1, 'route' => 2];

    public function __construct(private readonly ContainerInterface $services) {}

    /**
     * @param array<string, string> $controllerClasses
     * @return array{class-string, string}|null
     */
    public function describeController(mixed $controller, array $controllerClasses = []): ?array
    {
        if (is_string($controller) && function_exists($controller)) {
            return null;
        }

        [$target, $method] = match (true) {
            is_array($controller)  => [$controller[0] ?? null, $controller[1] ?? null],
            is_string($controller) => array_pad(explode('::', $controller, 2), 2, '__invoke'),
            default                => [$controller, '__invoke'],
        };

        if (!is_string($method)) {
            return null;
        }

        if (is_object($target) && !$target instanceof Closure && is_callable($controller)) {
            return method_exists($target, $method) ? [$target::class, $method] : null;
        }

        if (!is_string($target)) {
            return null;
        }

        $target = ltrim($target, '\\');
        $class  = $controllerClasses[$target] ?? $target;

        return class_exists($class) && method_exists($class, $method) ? [$class, $method] : null;
    }

    /**
     * @param class-string $class
     * @param list<string>|null $actionRoutes
     * @return list<Binding>
     */
    public function resolve(string $class, string $method, string $route, ?array $actionRoutes = null): array
    {
        if (!method_exists($class, $method)) {
            return [];
        }

        $targets  = ['controller' => new ReflectionClass($class), 'action' => new ReflectionMethod($class, $method)];
        $bindings = [];

        foreach ($targets as $targetScope => $target) {
            foreach ($target->getAttributes(UseMiddleware::class) as $attribute) {
                $binding      = $attribute->newInstance();
                $selector     = $binding->route;
                $selectsRoute = null !== $selector;

                if ($selectsRoute && 'controller' === $targetScope) {
                    throw new LogicException(sprintf(
                        'UseMiddleware route selectors are only valid on methods (%s, route "%s").',
                        $class,
                        $route,
                    ));
                }

                if ($selectsRoute && null !== $actionRoutes && !in_array($selector, $actionRoutes, true)) {
                    throw new LogicException(sprintf(
                        'Invalid middleware route selector "%s" on %s::%s. Valid routes: %s.',
                        $selector,
                        $class,
                        $method,
                        implode(', ', $actionRoutes),
                    ));
                }

                if ($selectsRoute && $selector !== $route) {
                    continue;
                }

                if (!$this->services->has($binding->service)) {
                    throw new LogicException(sprintf(
                        'Unknown scoped middleware service "%s" on %s::%s for route "%s". Register it with the roadrunner.http.middleware tag or enable autoconfiguration.',
                        $binding->service,
                        $class,
                        $method,
                        $route,
                    ));
                }

                $scope    = $selectsRoute ? 'route' : $targetScope;
                $id       = $binding->service;
                $previous = $bindings[$id] ?? null;

                if (
                    null !== $previous
                    && [self::SPECIFICITY[$scope], $binding->priority] <= [self::SPECIFICITY[$previous['scope']], $previous['priority']]
                ) {
                    continue;
                }

                // Insertion order breaks equal-priority ties for the winning declaration.
                unset($bindings[$id]);
                $bindings[$id] = ['id' => $id, 'scope' => $scope, 'priority' => $binding->priority];
            }
        }

        usort($bindings, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return $bindings;
    }

    public function get(string $id): Middleware
    {
        $middleware = $this->services->get($id);

        if (!$middleware instanceof Middleware) {
            throw new LogicException(sprintf('Service "%s" must implement %s.', $id, Middleware::class));
        }

        return $middleware;
    }
}
