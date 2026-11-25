<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\App;

use Roadrunner\Integration\Symfony\Http\Middleware\Attribute\UseMiddleware;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[UseMiddleware('fixture.class', priority: 10)]
final class MiddlewareController
{
    #[Route('/middleware/base', name: 'middleware_base', methods: ['GET'])]
    #[Route('/middleware/preview', name: 'middleware_preview')]
    #[UseMiddleware('fixture.action', priority: 10)]
    #[UseMiddleware('fixture.route_alias', priority: 20, route: 'middleware_preview')]
    public function show(): Response
    {
        return new JsonResponse(['controller' => 'called']);
    }

    #[Route('/middleware/stream', name: 'middleware_stream')]
    public function stream(): StreamedResponse
    {
        return new StreamedResponse(static function (): void {
            echo 'scoped stream';
        });
    }

    #[Route('/middleware/error', name: 'middleware_error')]
    public function error(): never
    {
        throw new HttpException(418, 'test middleware exception');
    }
}
