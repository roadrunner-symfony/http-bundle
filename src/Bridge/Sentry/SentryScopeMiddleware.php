<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\Sentry;

use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Sentry\State\HubInterface as Hub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SentryScopeMiddleware implements Middleware
{
    public function __construct(
        private readonly Hub $hub,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $this->hub->pushScope();

        try {
            return $next($request);
        } finally {
            $this->hub->popScope();
        }
    }
}
