<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Middleware;

use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ScopedMiddlewareSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ScopedMiddlewareResolver $resolver,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onControllerArguments', -1024]];
    }

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $worker  = PipelineMiddleware::workerRequest($request);

        if (null === $worker) {
            return;
        }

        $trace      = $worker['trace'];
        $route      = $request->attributes->get('_route');
        $controller = $event->getController();
        $target     = $this->resolver->describeController($controller);

        if (!is_string($route) || null === $target) {
            return;
        }

        $bindings = $this->resolver->resolve($target[0], $target[1], $route);

        if ([] === $bindings) {
            return;
        }

        $arguments     = $event->getArguments();
        $originalEvent = new ControllerArgumentsEvent(
            $event->getKernel(),
            $controller,
            $arguments,
            $request,
            $event->getRequestType(),
        );

        // Controller attribute metadata is available from Symfony 6.2 onward.
        if (method_exists($event, 'getAttributes')) { // @phpstan-ignore function.alreadyNarrowedType
            $originalEvent->setController($controller, $event->getAttributes());
        }

        $next = function (Request $current) use ($controller, $arguments, $originalEvent): Response {
            $result = $controller(...$arguments);

            if ($result instanceof Response) {
                return $result;
            }

            $view = new ViewEvent(
                $originalEvent->getKernel(),
                $current,
                $originalEvent->getRequestType(),
                $result,
                $originalEvent,
            );
            $this->dispatcher->dispatch($view, KernelEvents::VIEW);

            return $view->getResponse() ?? throw new ControllerDoesNotReturnResponseException(
                sprintf(
                    'The controller must return a "Symfony\Component\HttpFoundation\Response" object but it returned %s.%s',
                    get_debug_type($result),
                    null === $result ? ' Did you forget to add a return statement somewhere in your controller?' : '',
                ),
                $controller,
                __FILE__,
                __LINE__,
            );
        };

        foreach (array_reverse($bindings) as $binding) {
            $next = function (Request $current) use ($binding, $next, $request, $trace): Response {
                $middleware = $this->resolver->get($binding['id']);
                $trace?->record($binding['id'], $binding['scope']);

                return $middleware->process($current, static function (Request $forwarded) use ($next, $request): Response {
                    if ($forwarded !== $request) {
                        throw new InvalidArgumentException('Scoped middleware must pass the current Symfony Request to its continuation.');
                    }

                    return $next($forwarded);
                });
            };
        }

        $event->setController(static fn(): Response => $next($request));
        $event->setArguments([]);
    }
}
