<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DataCollector;

use Roadrunner\Integration\Symfony\Http\Middleware\MiddlewareTrace;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;
use WeakReference;

final class RoadRunnerHttpCollector extends DataCollector implements LateDataCollectorInterface
{
    public const NAME       = 'roadrunner_http';
    public const SERVICE_ID = 'roadrunner.http.data_collector';

    /** @var WeakReference<MiddlewareTrace>|null */
    private ?WeakReference $trace = null;

    public function __construct(
        private readonly RequestStack $requests,
    ) {}

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $trace = $this->requests->getCurrentRequest() === $request && null === $this->requests->getParentRequest()
            ? (PipelineMiddleware::workerRequest($request)['trace'] ?? null)
            : null;

        $this->trace = null === $trace ? null : WeakReference::create($trace);
        $this->data  = [
            'active'  => null !== $trace,
            'entries' => $trace?->entries() ?? [],
        ];
    }

    public function lateCollect(): void
    {
        if (null !== $trace = $this->trace?->get()) {
            $this->data['entries'] = $trace->entries();
            $this->trace           = null;
        }
    }

    public function reset(): void
    {
        $this->data  = [];
        $this->trace = null;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function isActive(): bool
    {
        return true === ($this->data['active'] ?? false);
    }

    /** @return list<array{id: string, scope: string}> */
    public function getEntries(): array
    {
        /** @var list<array{id: string, scope: string}> $entries */
        $entries = $this->data['entries'] ?? [];

        return $entries;
    }
}
