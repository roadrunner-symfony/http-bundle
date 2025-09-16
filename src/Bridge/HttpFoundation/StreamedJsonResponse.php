<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Generator;
use Roadrunner\Integration\Symfony\Http\Bridge\Encoder\Json\JsonStream;

final class StreamedJsonResponse extends StreamedResponse
{
    /**
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey, TValue> $data
     */
    public static function fromIterable(iterable $data = [], int $status = self::HTTP_OK, int $encodeOptions = 0): StreamedJsonResponse
    {
        $getLazyContent = static function (iterable $data, int $encodeOptions): Generator {
            yield from new JsonStream($data, $encodeOptions);
        };

        /**@phpstan-ignore-next-line  argument.type*/
        return new self($getLazyContent($data, $encodeOptions), $status, ['Content-Type' => 'application/json']);
    }


    public static function fromObject(object $data, int $status = self::HTTP_OK, int $encodeOptions = 0): StreamedJsonResponse
    {
        $getLazyContent = static function (object $data, int $encodeOptions): Generator {
            yield from new JsonStream($data, $encodeOptions);
        };

        /**@phpstan-ignore-next-line  argument.type*/
        return new self($getLazyContent($data, $encodeOptions), $status, ['Content-Type' => 'application/json']);
    }
}
