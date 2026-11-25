<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Functional;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Roadrunner\Integration\Symfony\Http\DataCollector\RoadRunnerHttpCollector;
use Roadrunner\Integration\Symfony\Http\Middleware\Attribute\UseMiddleware;
use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Roadrunner\Integration\Symfony\Http\Middleware\MiddlewareTrace;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareResolver;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareSubscriber;
use Roadrunner\Integration\Symfony\Http\Middleware\WorkerRequestRegistry;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

final class ScopedMiddlewareSubscriberTest extends TestCase
{
    private WorkerRequestRegistry $workerRequestRegistry;

    public function testUsesSymfonyViewAndResponseLifecycle(): void
    {
        $views         = 0;
        $responses     = 0;
        $dispatcher    = new EventDispatcher();
        $attribute     = new UseMiddleware('test.middleware', priority: 100);
        $hasAttributes = method_exists(ControllerArgumentsEvent::class, 'getAttributes');

        if ($hasAttributes) {
            $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, static function (ControllerArgumentsEvent $event) use ($attribute): void {
                $attributes = class_exists(ControllerArgumentsMetadata::class) ? [$attribute] : [UseMiddleware::class => [$attribute]];
                $event->setController($event->getController(), $attributes);
            });
        }

        $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, static function (ControllerArgumentsEvent $event): void {
            self::assertSame([$event->getRequest()], $event->getArguments());
        }, -10000);

        $dispatcher->addListener(KernelEvents::VIEW, static function (ViewEvent $event) use (&$views, $attribute, $hasAttributes): void {
            ++$views;

            if ($hasAttributes) {
                $metadata = $event->controllerMetadata ?? $event->controllerArgumentsEvent ?? null;
                self::assertNotNull($metadata);
                self::assertSame([$attribute], $metadata->getAttributes(UseMiddleware::class));
                self::assertSame($event->getRequest(), $metadata->getNamedArguments()['request']);
            }

            $event->setResponse(new JsonResponse($event->getControllerResult()));
        });
        $dispatcher->addListener(KernelEvents::RESPONSE, static function () use (&$responses): void {
            ++$responses;
        });
        $kernel   = $this->kernel($dispatcher);
        $response = $this->pipeline($kernel)->process($this->request());
        self::assertSame('{"original":true}', $response->getContent());
        self::assertSame('entered', $response->headers->get('X-Scoped'));
        self::assertSame(1, $views);
        self::assertSame(1, $responses);

        $response = $kernel->handle($this->request(), HttpKernel::SUB_REQUEST);
        self::assertFalse($response->headers->has('X-Scoped'));

        $response = $kernel->handle($this->request());
        self::assertFalse($response->headers->has('X-Scoped'));

        $request = $this->request();
        $request->attributes->remove('_route');
        $request->attributes->set('_canonical_route', 'example');
        $response = $this->pipeline($kernel)->process($request);
        self::assertFalse($response->headers->has('X-Scoped'));
    }

    public function testPreservesControllerErrorWithoutViewResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $kernel     = $this->kernel($dispatcher);
        $request    = $this->request();

        $workerRequestRegistry = $this->workerRequestRegistry;
        $dispatcher->addListener(KernelEvents::REQUEST, static function (RequestEvent $event) use ($workerRequestRegistry): void {
            $current = $event->getRequest();
            self::assertTrue($workerRequestRegistry->isActive($current));
        });

        try {
            $this->pipeline($kernel)->process($request);
            self::fail('Expected controller result to require a view response.');
        } catch (ControllerDoesNotReturnResponseException) {
            self::assertFalse($this->workerRequestRegistry->isActive($request));
        }
    }

    public function testOnlyMarksTheExactRequestForwardedByMiddleware(): void
    {
        $dispatcher = new EventDispatcher();
        $stack      = new RequestStack();
        $kernel     = $this->kernel($dispatcher, $stack);
        $collector  = new RoadRunnerHttpCollector($stack, $this->workerRequestRegistry);
        $profiled   = [];

        $dispatcher->addListener(KernelEvents::RESPONSE, static function (ResponseEvent $event) use ($collector, &$profiled): void {
            $collector->collect($event->getRequest(), $event->getResponse());
            $profiled[] = $collector->isActive();
        });
        $dispatcher->addListener(KernelEvents::VIEW, static function (ViewEvent $event): void {
            $event->setResponse(new JsonResponse($event->getControllerResult()));
        });
        $workerRequestRegistry = $this->workerRequestRegistry;
        $dispatcher->addListener(KernelEvents::REQUEST, static function (RequestEvent $event) use ($kernel, $workerRequestRegistry): void {
            $request = $event->getRequest();

            if (!$workerRequestRegistry->isActive($request)) {
                return;
            }

            $copy = $request->duplicate();
            self::assertFalse($workerRequestRegistry->isActive($copy));
            self::assertFalse($kernel->handle($copy)->headers->has('X-Scoped'));

            $request->initialize(attributes: [
                '_controller' => $request->attributes->get('_controller'),
                '_route'      => $request->attributes->get('_route'),
                'original'    => $request->attributes->get('original'),
            ]);
        });

        $global = new class($kernel, $workerRequestRegistry) implements Middleware {
            public function __construct(private readonly HttpKernel $kernel, private readonly WorkerRequestRegistry $workerRequestRegistry) {}

            public function process(Request $request, callable $next): Response
            {
                Assert::assertFalse($this->kernel->handle($request->duplicate())->headers->has('X-Scoped'));
                $replacement = $request->duplicate();
                $response    = $next($replacement);
                Assert::assertFalse($this->workerRequestRegistry->isActive($replacement));

                return $response;
            }
        };
        $trace    = new MiddlewareTrace();
        $pipeline = $this->pipeline($kernel, [$global], ['test.global']);

        self::assertSame('entered', $pipeline->process($this->request(), $trace)->headers->get('X-Scoped'));
        self::assertSame([
            ['id' => 'test.global', 'scope' => 'global'],
            ['id' => 'test.middleware', 'scope' => 'action'],
        ], $trace->entries());
        self::assertSame([false, false, true], $profiled);
    }

    #[UseMiddleware('test.middleware')]
    public function controller(Request $request): array
    {
        return ['original' => $request->attributes->getBoolean('original')];
    }

    public function testPreservesApplicationAttributeUsedAsControllerArgument(): void
    {
        $kernel  = $this->kernel(new EventDispatcher());
        $request = $this->request();
        $request->attributes->set('_controller', [$this, 'controllerWithApplicationAttribute']);
        $request->attributes->set('_roadrunner_http_worker', 'application value');

        $response = $this->pipeline($kernel)->process($request);

        self::assertSame('application value', $response->getContent());
        self::assertSame('entered', $response->headers->get('X-Scoped'));
        self::assertSame('application value', $request->attributes->get('_roadrunner_http_worker'));
    }

    #[UseMiddleware('test.middleware')]
    public function controllerWithApplicationAttribute(string $_roadrunner_http_worker): Response
    {
        return new Response($_roadrunner_http_worker);
    }

    private function kernel(EventDispatcher $dispatcher, ?RequestStack $stack = null): HttpKernel
    {
        $middleware = new class implements Middleware {
            public function process(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->headers->set('X-Scoped', 'entered');

                return $response;
            }
        };
        $resolver = new ScopedMiddlewareResolver(new ServiceLocator(['test.middleware' => static fn(): Middleware => $middleware]));
        $stack ??= new RequestStack();
        $this->workerRequestRegistry = new WorkerRequestRegistry();
        $dispatcher->addSubscriber(new ScopedMiddlewareSubscriber($resolver, $dispatcher, $this->workerRequestRegistry));

        return new HttpKernel($dispatcher, new ControllerResolver(), $stack, new ArgumentResolver());
    }

    /**
     * @param list<Middleware> $middlewares
     * @param list<string> $ids
     */
    private function pipeline(HttpKernel $kernel, array $middlewares = [], array $ids = []): PipelineMiddleware
    {
        $adapter = $this->createMock(KernelInterface::class);
        $adapter->method('handle')->willReturnCallback($kernel->handle(...));

        return new PipelineMiddleware($adapter, $middlewares, $ids, $this->workerRequestRegistry);
    }

    private function request(): Request
    {
        $request = Request::create('/example');
        $request->attributes->add(['_controller' => [$this, 'controller'], '_route' => 'example', 'original' => true]);

        return $request;
    }
}
