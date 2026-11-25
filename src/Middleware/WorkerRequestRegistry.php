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
use Symfony\Component\HttpFoundation\Response;
use WeakMap;

/** @internal */
final class WorkerRequestRegistry
{
    /** @var WeakMap<Request, array{trace: ?MiddlewareTrace}> */
    private readonly WeakMap $active;

    public function __construct()
    {
        $this->active = new WeakMap();
    }

    /** @param callable(Request): Response $handle */
    public function duringHandle(Request $request, ?MiddlewareTrace $trace, callable $handle): Response
    {
        $previous = $this->active[$request] ?? null;
        $this->active->offsetSet($request, ['trace' => $trace]);

        try {
            return $handle($request);
        } finally {
            if (null === $previous) {
                $this->active->offsetUnset($request);
            } else {
                $this->active->offsetSet($request, $previous);
            }
        }
    }

    public function isActive(Request $request): bool
    {
        return isset($this->active[$request]);
    }

    public function traceFor(Request $request): ?MiddlewareTrace
    {
        return $this->active[$request]['trace'] ?? null;
    }
}
