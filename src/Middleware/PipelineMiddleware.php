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
use WeakReference;

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
    public function process(Request $request): Response
    {
        return $this->dispatch($request, $this->kernel->handle(...), null);
    }

    /** @internal */
    public function processWorkerRequest(Request $request, ?MiddlewareTrace $trace): Response
    {
        return $this->dispatch($request, function (Request $forwarded) use ($trace): Response {
            $attributes = $forwarded->attributes;
            $key        = self::WORKER_REQUEST_ATTRIBUTE;
            $present    = $attributes->has($key);
            $previous   = $attributes->get($key);

            $attributes->set($key, [
                'request' => WeakReference::create($forwarded),
                'trace'   => null === $trace ? null : WeakReference::create($trace),
            ]);

            try {
                return $this->kernel->handle($forwarded);
            } finally {
                // Request::initialize() may have replaced the attribute bag during handling.
                if ($present) {
                    $forwarded->attributes->set($key, $previous);
                } else {
                    $forwarded->attributes->remove($key);
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
        $marker = $request->attributes->get(self::WORKER_REQUEST_ATTRIBUTE);

        if (!is_array($marker) || !(($marker['request'] ?? null) instanceof WeakReference) || $marker['request']->get() !== $request) {
            return null;
        }

        $reference = $marker['trace'] ?? null;
        $trace     = $reference instanceof WeakReference ? $reference->get() : null;

        return ['trace' => $trace instanceof MiddlewareTrace ? $trace : null];
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
