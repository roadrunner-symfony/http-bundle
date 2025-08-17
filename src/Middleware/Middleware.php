<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

interface Middleware
{
    /**
     * @param callable(Request): Response $next
     *
     * @throws Throwable
     */
    public function process(Request $request, callable $next): Response;
}
