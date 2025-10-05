<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Generator;

use function is_array;
use function is_object;

use const JSON_NUMERIC_CHECK;
use const JSON_THROW_ON_ERROR;

use JsonException;
use JsonSerializable;
use Symfony\Component\HttpFoundation\JsonResponse;

class StreamedJsonResponse extends StreamedResponse
{
    private const PLACEHOLDER = '__symfony_json__';

    /**
     * @param mixed[]                        $data            JSON Data containing PHP generators which will be streamed as list of data or a Generator
     * @param int                            $status          The HTTP status code (200 "OK" by default)
     * @param array<string, string|string[]> $headers         An array of HTTP headers
     * @param int                            $encodingOptions Flags for the json_encode() function
     */
    public function __construct(
        private readonly iterable $data,
        int $status = 200,
        array $headers = [],
        private int $encodingOptions = JsonResponse::DEFAULT_ENCODING_OPTIONS,
    ) {
        parent::__construct($this->stream(), $status, $headers);

        if (!$this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }


    private function stream(): Generator
    {
        $jsonEncodingOptions = JSON_THROW_ON_ERROR | $this->encodingOptions;
        $keyEncodingOptions  = $jsonEncodingOptions & ~JSON_NUMERIC_CHECK;

        yield from $this->streamData($this->data, $jsonEncodingOptions, $keyEncodingOptions);
    }


    /**
     * @throws JsonException
     *
     * @return Generator<string>
     */
    private function streamData(mixed $data, int $jsonEncodingOptions, int $keyEncodingOptions): Generator
    {
        if (is_array($data)) {
            foreach ($this->streamArray($data, $jsonEncodingOptions, $keyEncodingOptions) as $item) {
                yield $item;
            }

            return;
        }

        if (is_iterable($data) && !$data instanceof JsonSerializable) {
            foreach ($this->streamIterable($data, $jsonEncodingOptions, $keyEncodingOptions) as $item) {
                yield $item;
            }

            return;
        }

        yield json_encode($data, $jsonEncodingOptions | JSON_THROW_ON_ERROR);
    }


    /**
     * @param array<mixed> $data
     *
     * @throws JsonException
     *
     * @return Generator<string>
     */
    private function streamArray(array $data, int $jsonEncodingOptions, int $keyEncodingOptions): Generator
    {
        $generators = [];

        array_walk_recursive($data, static function (&$item, $key) use (&$generators): void {
            if (self::PLACEHOLDER === $key) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $key;
            }

            // generators should be used but for better DX all kind of Traversable and objects are supported
            if (is_object($item)) {
                $generators[] = $item;
                $item         = self::PLACEHOLDER;
            } elseif (self::PLACEHOLDER === $item) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $item;
            }
        });

        $jsonParts = explode('"' . self::PLACEHOLDER . '"', json_encode($data, $jsonEncodingOptions | JSON_THROW_ON_ERROR));

        foreach ($generators as $index => $generator) {
            // send first and between parts of the structure

            yield $jsonParts[$index];

            foreach ($this->streamData($generator, $jsonEncodingOptions, $keyEncodingOptions) as $child) {
                yield $child;
            }
        }

        // send last part of the structure
        yield $jsonParts[array_key_last($jsonParts)];
    }


    /**
     * @param iterable<scalar, mixed> $iterable
     *
     * @throws JsonException
     *
     * @return Generator<string>
     */
    private function streamIterable(iterable $iterable, int $jsonEncodingOptions, int $keyEncodingOptions): Generator
    {
        $isFirstItem = true;
        $startTag    = '[';

        foreach ($iterable as $key => $item) {
            if ($isFirstItem) {
                $isFirstItem = false;
                // depending on the first elements key the generator is detected as a list or map
                // we can not check for a whole list or map because that would hurt the performance
                // of the streamed response which is the main goal of this response class
                if (0 !== $key) {
                    $startTag = '{';
                }

                yield $startTag;
            } else {
                // if not first element of the generic, a separator is required between the elements
                yield ',';
            }

            if ('{' === $startTag) {
                yield json_encode((string) $key, $keyEncodingOptions) . ':';
            }

            foreach ($this->streamData($item, $jsonEncodingOptions, $keyEncodingOptions) as $child) {
                yield $child;
            }
        }

        if ($isFirstItem) { // indicates that the generator was empty
            yield '[';
        }

        yield '[' === $startTag ? ']' : '}';
    }
}
