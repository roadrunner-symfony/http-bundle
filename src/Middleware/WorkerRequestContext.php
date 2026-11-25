<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;

/** @internal */
final class WorkerRequestContext
{
    public function __construct(
        private ?Request $owner,
        private ?MiddlewareTrace $trace,
    ) {}

    /** @return array{trace: ?MiddlewareTrace}|null */
    public function forRequest(Request $request): ?array
    {
        return $this->owner === $request ? ['trace' => $this->trace] : null;
    }

    public function clear(): void
    {
        $this->owner = null;
        $this->trace = null;
    }
}
