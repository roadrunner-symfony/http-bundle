<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Symfony\Component\HttpKernel\Event;

final class ControllerArgumentsEvent
{
    /**
     * @template T of object
     * @param class-string<T>|'*'|null $className The wildcard format is available from Symfony 8.1.
     * @return ($className is null ? array<class-string, list<object>> : ($className is '*' ? list<object> : list<T>))
     */
    public function getAttributes(?string $className = null): array {}

    /**
     * @param array<class-string, list<object>>|list<object>|null $attributes Symfony 8.1 expects a flat list.
     */
    public function setController(callable $controller, ?array $attributes = null): void {}
}
