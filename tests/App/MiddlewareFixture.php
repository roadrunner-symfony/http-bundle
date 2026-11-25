<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\App;

use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MiddlewareFixture implements Middleware
{
    public function __construct(private readonly string $label) {}

    public function process(Request $request, callable $next): Response
    {
        if ('global' === $this->label && '/middleware/pre-kernel' === $request->getPathInfo()) {
            return new Response('pre-kernel');
        }

        if ('global' === $this->label && str_starts_with($request->getPathInfo(), '/middleware/')) {
            $request = clone $request;
        }

        if ('route' === $this->label && $request->query->getBoolean('stop')) {
            return new Response('stopped');
        }

        $response = $next($request);
        $response->headers->set('X-Middleware', $this->label, false);

        return $response;
    }
}
