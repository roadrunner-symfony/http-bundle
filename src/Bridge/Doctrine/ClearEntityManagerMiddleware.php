<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClearEntityManagerMiddleware implements Middleware
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry
    ) {}

    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } finally {
            foreach ($this->managerRegistry->getManagers() as $manager) {
                $manager->clear();
            }
        }
    }
}
