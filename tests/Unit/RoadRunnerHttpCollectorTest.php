<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Unit;

use PHPUnit\Framework\TestCase;
use Roadrunner\Integration\Symfony\Http\DataCollector\RoadRunnerHttpCollector;
use Roadrunner\Integration\Symfony\Http\Middleware\MiddlewareTrace;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class RoadRunnerHttpCollectorTest extends TestCase
{
    public function testDistinguishesEmptyWorkerTraceFromOtherRequests(): void
    {
        $stack     = new RequestStack();
        $collector = new RoadRunnerHttpCollector($stack);
        $request   = new Request();
        $response  = new Response();
        $trace     = new MiddlewareTrace();
        $kernel    = $this->createMock(KernelInterface::class);
        $kernel->expects(self::once())->method('handle')->willReturnCallback(
            static function (Request $current) use ($stack, $collector, $response): Response {
                $stack->push($current);

                try {
                    $collector->collect($current, $response);
                    self::assertTrue($collector->isActive());
                    self::assertSame([], $collector->getEntries());
                } finally {
                    $stack->pop();
                }

                return $response;
            },
        );

        (new PipelineMiddleware($kernel, []))->processWorkerRequest($request, $trace);
        $profileCollector = clone $collector;
        $stack->push($request);
        $collector->collect($request, $response);
        self::assertFalse($collector->isActive());
        $stack->pop();

        $trace->record('same', 'global');
        $trace->record('same', 'action');

        $profileCollector->lateCollect();
        $profileCollector = unserialize(serialize($profileCollector));

        self::assertTrue($profileCollector->isActive());
        self::assertSame([
            ['id' => 'same', 'scope' => 'global'],
            ['id' => 'same', 'scope' => 'action'],
        ], $profileCollector->getEntries());
    }
}
