# RoadRunner Http-Bundle

RoadRunner is a high-performance PHP application server and process manager with a plugin architecture.

## Features

- **Middleware**
- **Streamed responses**
- **Sentry** pushes and pops a scope for each request when [`SentryBundle`](https://github.com/getsentry/sentry-symfony) is in use.
- **Doctrine** clears open entity managers and checks that the connection is still usable after each request when [`DoctrineBundle`](https://github.com/doctrine/DoctrineBundle) is in use.

## Requirements:

- php >= 8.1
- symfony >= 6.0

1. Set up the recipes.

[Recipe installation instructions](https://github.com/roadrunner-symfony/recipes?tab=readme-ov-file#installation)

2. Install the package.

```bash
composer req http
```

3. Configure the following files.
   - docker-compose-roadrunner.yml
   - Dockerfile
   - .rr.http.yaml
   - config/packages/roadrunner.http.yaml

## Sentry integrations

Install Sentry.

```bash
composer require sentry
```

The following option requires [`SentryBundle`](https://github.com/getsentry/sentry-symfony).

- `useSentryIntegration` enables per-request Sentry scopes.

Example configuration

**Specific worker**

```yaml
road_runner_http:
  useSentryIntegration: true
```

## Doctrine integrations

Install Doctrine.

```bash
composer require orm
```

[`DoctrineBundle`](https://github.com/doctrine/DoctrineBundle) enables these configuration options.

- `useDoctrineIntegration` enables the Doctrine integration.
- `useLoggingDoctrineOpenTransaction` reports unclosed transactions to Monolog.
- `useTrackingSentryDoctrineOpenTransaction` reports unclosed transactions to Sentry.

Each option takes a list of entity manager names.

Example configuration

```yaml
road_runner_http:
  useDoctrineIntegration:
    - default
    - test

  useLoggingDoctrineOpenTransaction:
    - default
    - test

  useTrackingSentryDoctrineOpenTransaction:
    - default
```

## Route and controller middleware

Register the middleware as a Symfony service that implements `Roadrunner\Integration\Symfony\Http\Middleware\Middleware`, then enable autoconfiguration or add the `roadrunner.http.middleware` tag. Use the service ID to reference a private service or a configured alias.

```php
use Roadrunner\Integration\Symfony\Http\Middleware\Attribute\UseMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[UseMiddleware(ControllerMiddleware::class, priority: 10)]
final class OrderController
{
    #[Route('/orders', name: 'orders_list')]
    #[UseMiddleware(ListMiddleware::class)]
    public function list(): Response
    {
        // ...
    }

    #[Route('/orders/{id}', name: 'orders_show')]
    #[Route('/orders/{id}/preview', name: 'orders_preview')]
    #[UseMiddleware(PreviewMiddleware::class, priority: 20, route: 'orders_preview')]
    public function show(int $id): Response
    {
        // ...
    }
}
```

A class-level attribute applies to every routed action on the controller. On a method, it applies to all routes for that action. Set `route` to an exact route name to select just that route. For a localized route, use its canonical name.

Global middleware runs first, in configuration order. Scoped middleware wraps the controller call after routing, request listeners, and argument resolution have run. Higher priorities run first. The default is `0`. At equal priority, class bindings run before method bindings, and bindings on the same target follow declaration order.

When a request listener calls `Request::initialize()` or replaces the request's attribute bag, carry over the existing attributes so the request keeps its middleware and profiler context.

When a scoped service ID appears more than once, the route-specific binding takes precedence, followed by the action binding and then the class binding. Among bindings in the same scope, the highest priority wins. If priorities are equal, the first declaration wins. A service registered both globally and in a scope can run in both places.

Pass the same Symfony request instance to `$next($request)` that the scoped middleware received. If middleware returns a response without calling `$next`, the rest of the chain and the controller are skipped.

```bash
bin/console debug:middleware
bin/console debug:middleware orders_preview
```

The command lists the configured middleware in the order it would run, including global middleware registered by integrations. In Symfony Web Profiler, the **RoadRunner HTTP** panel lists the middleware that ran for the profiled worker request. The panel is available even if the chain is empty. Symfony does not create a profile when global middleware returns a response before the kernel starts.

## Streamed Response

The bundle supports the standard Symfony responses:

```php
Symfony\Component\HttpFoundation\StreamedResponse
Symfony\Component\HttpFoundation\StreamedJsonResponse
```

The bundle provides these RoadRunner implementations:

```php
Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedResponse
Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedJsonResponse
```

**Use the `Roadrunner\Integration\Symfony` implementations** where possible. They reduce I/O overhead and improve streaming performance in RoadRunner.
</br>**Standard Symfony responses remain fully supported.** Third-party libraries that return `Symfony\Component\HttpFoundation` responses continue to work.

Examples

- [`Symfony\Component\HttpFoundation\StreamedResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L171)
- [`Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L158)
- [`Symfony\Component\HttpFoundation\StreamedJsonResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L188)
- [`Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedJsonResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L188)
