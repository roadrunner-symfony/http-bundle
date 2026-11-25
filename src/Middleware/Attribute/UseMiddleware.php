<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class UseMiddleware
{
    /**
     * @param non-empty-string $service
     * @param non-empty-string|null $route
     */
    public function __construct(
        public readonly string $service,
        public readonly int $priority = 0,
        public readonly ?string $route = null,
    ) {}
}
