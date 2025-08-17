<?php

/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http;

use Closure;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 * @phpstan-type Handler \Closure(non-empty-string,  class-string, array<int, non-empty-string>|array{}): bool
 */
final class InstalledVersions
{
    /**
     */
    private static ?Closure $handler = null;


    /**
     */
    public static function setHandler(?Closure $handler = null): void
    {
        self::$handler = $handler;
    }


    /**
     * @param non-empty-string                            $package
     * @param class-string                                $class
     * @param non-empty-array<int, class-string>|array{}  $parentPackages
     */
    public static function willBeAvailable(string $package, string $class, array $parentPackages = []): bool
    {
        $handler = self::$handler;

        if ($handler) {
            return $handler($package, $class, $parentPackages);
        }

        return ContainerBuilder::willBeAvailable($package, $class, $parentPackages);
    }
}
