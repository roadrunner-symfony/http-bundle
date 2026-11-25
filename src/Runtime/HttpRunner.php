<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2025, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Runtime;

use Exception;
use Roadrunner\Integration\Symfony\Http\Middleware\MiddlewareTrace;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Symfony\Component\HttpKernel\TerminableInterface as Terminable;
use Symfony\Component\Runtime\RunnerInterface as Runner;
use Throwable;

final class HttpRunner implements Runner
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly HttpWorker $worker,
        private readonly PipelineMiddleware $pipeline,
        private readonly bool $traceMiddleware = false,
    ) {}

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function run(): int
    {
        // Initialize routing and other lazy services that Symfony has.
        // Reduces first real request response time more than 50%, YMMW
        $this->kernel->handle(
            new Request(attributes: [
                'rr_dummy_request' => true,
                '_controller'      => RedirectController::class,
                '_route_params'    => [
                    'path' => '/',
                ],
            ]),
            Kernel::SUB_REQUEST,
            false
        );


        while (true) {
            try {
                $request = $this->worker->waitRequest();

                if ($request === null) {
                    break;
                }
            } catch (Throwable $e) {
                $this->worker->sendError($e, $this->kernel, $request ?? null);

                continue;
            }

            $this->handleRequest($request);
        }

        return 1;
    }

    private function handleRequest(Request $request): void
    {
        $trace = $this->traceMiddleware ? new MiddlewareTrace() : null;

        try {
            $response = $this->pipeline->processWorkerRequest($request, $trace);
        } catch (Throwable $e) {
            try {
                $this->worker->respondThrowable($e);
            } finally {
                $this->kernel->boot();
            }

            return;
        }

        try {
            $this->worker->respond($response);
        } catch (Throwable $e) {
            $this->worker->sendError($e, $this->kernel, $request);

            return;
        }


        if ($this->kernel instanceof Terminable) {
            try {
                $this->kernel->terminate($request, $response);
            } catch (Throwable $e) {
                $this->worker->sendError($e, $this->kernel, $request);
            }
        }

        $this->kernel->boot();
    }
}
