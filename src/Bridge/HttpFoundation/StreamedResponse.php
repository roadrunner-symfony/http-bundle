<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Generator;
use Symfony\Component\HttpFoundation\Response;

class StreamedResponse extends Response
{
    /**
     * @param Generator<string> $lazyContent
     */
    public function __construct(
        public readonly Generator $lazyContent,
        int $status = self::HTTP_OK,
        array $headers = []
    ) {

        parent::__construct(status: $status, headers: array_merge(['X-Accel-Buffering' => 'no'], $headers));
    }
}
