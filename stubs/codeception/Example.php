<?php

declare(strict_types=1);

namespace Codeception;

use ArrayAccess;

/**
 * @template TArray of array
 * @implements ArrayAccess<key-of<TArray>, value-of<TArray>>
 */
final class Example implements ArrayAccess
{
    /**
     * @param TArray $data
     */
    public function __construct(private $data) {}

    /**
     * @param key-of<TArray> $offset
     */
    public function offsetExists(mixed $offset): bool {}

    /**
     * @template TOffset of key-of<TArray>
     * @param TOffset $offset
     * @return TArray[TOffset]
     */
    public function offsetGet(mixed $offset): mixed {}

    /**
     * @template TOffset of key-of<TArray>|null
     * @param TOffset $offset
     * @param TArray[TOffset] $value
     */
    public function offsetSet(mixed $offset, mixed $value): void {}

    /**
     * @template TOffset of key-of<TArray>
     * @param TOffset $offset
     */
    public function offsetUnset(mixed $offset): void {}
}
