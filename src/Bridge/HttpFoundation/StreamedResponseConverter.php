<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */
declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation;

use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface StreamedResponseConverter
{
    public function convert(StreamedResponse $response): Generator;
}
