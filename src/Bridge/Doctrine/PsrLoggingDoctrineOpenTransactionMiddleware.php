<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface as Logger;
use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PsrLoggingDoctrineOpenTransactionMiddleware implements Middleware
{
    public function __construct(
        private readonly Logger $logger,
        private readonly Connection $connection,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $initialTransactionLevel = $this->connection->getTransactionNestingLevel();

        try {
            return $next($request);
        } finally {
            if ($this->connection->getTransactionNestingLevel() > $initialTransactionLevel) {
                $this->logger->critical('A activity opened a transaction but did not close it.', [
                    'request' => [
                        'uri'     => $request->getUri(),
                        'method'  => $request->getMethod(),
                        'headers' => $request->headers->all(),
                    ],
                ]);
            }
        }
    }
}
