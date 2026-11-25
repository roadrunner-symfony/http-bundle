<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Roadrunner\Integration\Symfony\Http\Middleware\Attribute\UseMiddleware;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareResolver;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[UseMiddleware('base', priority: 5)]
#[UseMiddleware('duplicate', priority: 99)]
final class ScopedMiddlewareResolverTest extends TestCase
{
    public function testResolvesWithoutInstantiatingServices(): void
    {
        $resolver = $this->resolver();

        self::assertSame([
            ['id' => 'base', 'scope' => 'controller', 'priority' => 5],
            ['id' => 'action', 'scope' => 'action', 'priority' => 5],
            ['id' => 'duplicate', 'scope' => 'route', 'priority' => 5],
        ], $resolver->resolve(self::class, '__invoke', 'preview', ['show', 'preview']));

        self::assertSame([
            ['id' => 'base', 'scope' => 'controller', 'priority' => 5],
            ['id' => 'action', 'scope' => 'action', 'priority' => 5],
            ['id' => 'duplicate', 'scope' => 'action', 'priority' => -2],
        ], $resolver->resolve(self::class, '__invoke', 'show', ['show', 'preview']));
    }

    public function testReportsInvalidRouteSelector(): void
    {
        $this->expectExceptionMessage('Invalid middleware route selector "preview"');
        $this->resolver()->resolve(self::class, '__invoke', 'show', ['show']);
    }

    public function testReportsUnknownService(): void
    {
        $this->expectExceptionMessage('Unknown scoped middleware service "missing"');
        $this->resolver()->resolve(self::class, 'missing', 'show');
    }

    #[UseMiddleware('action', priority: 5)]
    #[UseMiddleware('duplicate', priority: -10, route: 'preview')]
    #[UseMiddleware('duplicate', priority: 5, route: 'preview')]
    #[UseMiddleware('duplicate', priority: -2)]
    public function __invoke(): void {}

    #[UseMiddleware('missing')]
    public function missing(): void {}

    private function resolver(): ScopedMiddlewareResolver
    {
        $factory = static fn(): never => throw new LogicException('Inspection must be lazy.');

        return new ScopedMiddlewareResolver(new ServiceLocator(array_fill_keys(['base', 'duplicate', 'action'], $factory)));
    }
}
