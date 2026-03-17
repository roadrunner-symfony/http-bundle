<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */
declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Amp\Pipeline\Queue;
use Closure;
use Exception;
use Generator;
use IteratorAggregate;

use function ob_start;

use Revolt\EventLoop;
use Revolt\EventLoop\FiberLocal;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Traversable;

final class AmpStreamedResponseConverter implements StreamedResponseConverter
{
    /**
     * @throws Exception
     */
    public function convert(StreamedResponse $response): Generator
    {
        yield from (new OutputStreamIterator($response->sendContent(...)))->getIterator();
    }
}

/**
 * @internal
 *
 * @implements IteratorAggregate<string>
 */
final class OutputStreamIterator implements IteratorAggregate
{
    /** @var FiberLocal<Queue<string>|null> */
    private static FiberLocal $fiberLocal;

    public function __construct(private readonly Closure $closure)
    {
        if (isset(self::$fiberLocal)) {
            return;
        }

        /** @var FiberLocal<Queue<string>|null> $fiber */
        $fiber = new FiberLocal(static fn() => null);

        self::$fiberLocal = $fiber;

        ob_start(static function (string $chunk): string {
            $queue = self::$fiberLocal->get();

            if ($queue === null) {
                return $chunk;
            }

            if ($chunk != '') {
                $queue->push($chunk);
            }

            return '';
        }, 1);
    }

    public function getIterator(): Traversable
    {
        $closure = $this->closure;
        /** @var Queue<string> $queue */
        $queue = new Queue();

        EventLoop::queue(static function () use ($closure, $queue): void {
            self::$fiberLocal->set($queue);
            try {
                $closure();
            } finally {
                $queue->complete();
            }
        });

        return $queue->iterate();
    }
}
