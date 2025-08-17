<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\HubInterface as Hub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SentryDoctrineOpenTransactionMiddleware implements Middleware
{
    public function __construct(
        private readonly Hub $hub,
        private readonly Connection $connection,
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $initialTransactionLevel = $this->connection->getTransactionNestingLevel();

        try {
            return $next($request);
        } finally {
            if ($this->connection->getTransactionNestingLevel() > $initialTransactionLevel) {
                $event = Event::createEvent();

                $event->setRequest([
                    'uri'     => $request->getUri(),
                    'method'  => $request->getMethod(),
                    'headers' => $request->headers->all(),
                ]);

                $event->setLevel(Severity::error());
                $event->setMessage('A activity opened a transaction but did not close it.');

                $this->hub->captureEvent($event);
            }
        }
    }
}
