<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Runtime;

use Exception;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface as Runner;
use Throwable;

final class HttpRunner implements Runner
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly HttpWorker $worker,
        private readonly PipelineMiddleware $pipeline,
    ) {}

    /**
     * @throws Exception
     */
    public function run(): int
    {
        $isTerminableKernel = $this->kernel instanceof TerminableInterface;


        // Initialize routing and other lazy services that Symfony has.
        // Reduces first real request response time more than 50%, YMMW
        $this->kernel->handle(
            new Request(attributes: [
                'rr_dummy_request' => true,
                '_controller'      => RedirectController::class,
                '_route_params'    => [
                    'path' => '/',
                ],
            ])
        );


        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            try {
                $request = $this->worker->waitRequest();

                if ($request === null) {
                    continue;
                }
            } catch (Throwable $e) {
                $this->worker->respondThrowable($e);

                continue;
            }


            try {
                $response = $this->pipeline->process($request);
            } catch (Throwable $e) {
                try {
                    $this->worker->respondThrowable($e);
                } finally {
                    $this->kernel->boot();
                }

                continue;
            }

            $this->worker->respond($response);

            if ($isTerminableKernel) {
                try {
                    /** @phpstan-ignore method.notFound */
                    $this->kernel->terminate($request, $response);
                } catch (Throwable $e) {
                    ///
                }
            }

            $this->kernel->boot();
        }
    }
}
