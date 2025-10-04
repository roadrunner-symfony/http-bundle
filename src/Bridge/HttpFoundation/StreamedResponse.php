<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Generator;
use Stringable;
use Symfony\Component\HttpFoundation\Response;

class StreamedResponse extends Response
{
    /**
     * @param array<string, array<string>|string>                                   $headers
     * @param Generator<mixed, scalar|Stringable, mixed, Stringable|scalar|null> $lazyContent
     */
    public function __construct(
        public readonly Generator $lazyContent,
        int $status = self::HTTP_OK,
        array $headers = []
    ) {

        parent::__construct(status: $status, headers: $headers);
    }
}
