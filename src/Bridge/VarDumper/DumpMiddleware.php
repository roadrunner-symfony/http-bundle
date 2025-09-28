<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\VarDumper;

use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\DumpListener;

final class DumpMiddleware implements Middleware
{
    private bool $activate;

    public function __construct(
        private readonly DumpListener $listener,
    ) {
        $this->activate = false;
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->activate) {
            $this->listener->configure();

            $this->activate = true;
        }

        return $next($request);
    }
}
