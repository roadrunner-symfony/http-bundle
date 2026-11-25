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
    private const WORKER_REQUEST_ATTRIBUTE = '_roadrunner_http_worker';

    /**
     * @param array<int, Middleware> $middlewares
     * @param array<int, string> $middlewareIds
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly array $middlewares,
        private readonly array $middlewareIds = [],
    ) {}

    /** @throws Throwable */
    public function process(Request $request, ?MiddlewareTrace $trace = null): Response
    {
        return $this->dispatch($request, function (Request $forwarded) use ($trace): Response {
            $attributes = $forwarded->attributes;
            $key        = self::WORKER_REQUEST_ATTRIBUTE;
            $present    = $attributes->has($key);
            $previous   = $attributes->get($key);
            $context    = new WorkerRequestContext($forwarded, $trace);
            $attributes->set($key, $context);

            try {
                return $this->kernel->handle($forwarded);
            } finally {
                // Copies of the request may still hold this context.
                $context->clear();

                foreach ([$attributes, $forwarded->attributes] as $bag) {
                    if ($bag->get($key) !== $context) {
                        continue;
                    }

                    if ($present) {
                        $bag->set($key, $previous);
                    } else {
                        $bag->remove($key);
                    }
                }
            }
        }, $trace);
    }

    /**
     * @internal
     * @return array{trace: ?MiddlewareTrace}|null
     */
    public static function workerRequest(Request $request): ?array
    {
        $context = $request->attributes->get(self::WORKER_REQUEST_ATTRIBUTE);

        return $context instanceof WorkerRequestContext ? $context->forRequest($request) : null;
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
