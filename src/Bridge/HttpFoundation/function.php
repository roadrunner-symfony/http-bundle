<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2025, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Fiber;
use Generator;

use function ob_end_clean;
use function ob_start;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @internal
 *
 * @throws Throwable
 */
function echoToGenerator(StreamedResponse $response): Generator
{
    $fiber = new Fiber(static function () use ($response): void {
        ob_start(static function (string $chunk): string {
            Fiber::suspend($chunk);

            return '';
        });

        $response->sendContent();

        ob_end_clean();
    });

    $value = $fiber->start();

    while ($fiber->isSuspended()) {
        if ($value !== '') {
            yield $value;
        }

        $value = $fiber->resume();
    }
}
