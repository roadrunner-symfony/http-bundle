<?php

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
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly array $middlewares,
    ) {}

    /**
     * @throws Throwable
     */
    public function process(Request $request): Response
    {
        $middlewares = $this->middlewares;
        $middleware  = array_shift($middlewares);

        if (null == $middleware) {
            return $this->kernel->handle($request);
        }

        return $middleware->process($request, [new self($this->kernel, $middlewares), 'process']);
    }
}
