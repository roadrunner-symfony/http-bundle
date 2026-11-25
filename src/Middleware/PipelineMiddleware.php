<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2025, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Throwable;

final class PipelineMiddleware
{
    /**
     * @param array<int, Middleware> $middlewares
     * @param array<int, string> $middlewareIds
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly array $middlewares,
        private readonly array $middlewareIds = [],
        private readonly ?WorkerRequestRegistry $workerRequestRegistry = null,
    ) {}

    /** @throws Throwable */
    public function process(Request $request, ?MiddlewareTrace $trace = null): Response
    {
        $handle                = $this->kernel->handle(...);
        $workerRequestRegistry = $this->workerRequestRegistry;

        $next = null === $workerRequestRegistry
            ? $handle
            : static fn(Request $forwarded): Response => $workerRequestRegistry->duringHandle($forwarded, $trace, $handle);

        return $this->dispatch($request, $next, $trace);
    }

    /** @param callable(Request): Response $next */
    private function dispatch(Request $request, callable $next, ?MiddlewareTrace $trace): Response
    {
        foreach (array_reverse($this->middlewares, true) as $index => $middleware) {
            $next = function (Request $current) use ($middleware, $index, $next, $trace): Response {
                $trace?->record($this->middlewareIds[$index] ?? $middleware::class, 'global');

                return $middleware->process($current, $next);
            };
        }

        return $next($request);
    }
}
