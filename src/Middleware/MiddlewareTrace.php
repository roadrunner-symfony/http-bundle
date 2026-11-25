<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware;

/** @internal */
final class MiddlewareTrace
{
    /** @var list<array{id: string, scope: string}> */
    private array $entries = [];

    public function record(string $id, string $scope): void
    {
        $this->entries[] = ['id' => $id, 'scope' => $scope];
    }

    /** @return list<array{id: string, scope: string}> */
    public function entries(): array
    {
        return $this->entries;
    }
}
