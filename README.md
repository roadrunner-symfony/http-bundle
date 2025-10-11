# RoadRunner Http-Bundle

RoadRunner is a high-performance PHP application server and process manager, designed with extensibility in mind through its utilization of plugins.



## Features

- **Middleware**
- **Supported streaming response**
- **Sentry**: Push/pop scope (if the [`SentryBundle`](https://github.com/getsentry/sentry-symfony) use)
- **Doctrine**: clear opened managers and check connection is still usable after each request (
  if [`DoctrineBundle`](https://github.com/doctrine/DoctrineBundle) is use)


## Requirements:

- php >= 8.1
- symfony >= 6.0





1. Connect recipes

[`See link recipies repository`](https://github.com/roadrunner-symfony/recipes?tab=readme-ov-file#installation)


2. Install package

```bash
composer req http
```

3. Configure:
 - docker-compose-roadrunner.yml
 - Dockerfile
 - .rr.http.yaml
 - config/packages/roadrunner.http.yaml


## Sentry integrations

Install packages:

```bash
composer require sentry
```

If [`SentryBundle`](https://github.com/getsentry/sentry-symfony) is use, the following parameters is available to you:

- `useSentryIntegration` - Connect integration

Example config:

**Specific worker**

```yaml
road_runner_http:
  useSentryIntegration: true
```


## Doctrine integrations

Install packages:

```bash
composer require orm temporal-doctrine
```


If [`DoctrineBundle`](https://github.com/doctrine/DoctrineBundle) is use, the following parameters is available to you:

- `useDoctrineIntegration` - Connect integration
- `useLoggingDoctrineOpenTransaction`        - Connect middleware that report unclosed transaction to monolog
- `useTrackingSentryDoctrineOpenTransaction` - Connect middleware that report unclosed transaction to sentry

These parameters accept a list of entity-managers

Example config:


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


## Streamed Response



The bundle supports the standard Symfony responses:

```php
Symfony\Component\HttpFoundation\StreamedResponse
Symfony\Component\HttpFoundation\StreamedJsonResponse
```

It also provides its own optimized implementations:

```php
Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedResponse
Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedJsonResponse
```


**We recommend using the Roadrunner\Integration\Symfony implementations** whenever possible, as they eliminate unnecessary I/O overhead and offer better performance in the RoadRunner environment.
</br>**At the same time, full backward compatibility** with the standard Symfony responses is preserved, ensuring that third-party libraries built for Symfony\Component\HttpFoundation continue to work seamlessly.


Examples of use:
- [`Symfony\Component\HttpFoundation\StreamedResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L171)
- [`Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L158)
- [`Symfony\Component\HttpFoundation\StreamedJsonResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L188)
- [`Roadrunner\Integration\Symfony\Http\Bridge\HttpFoundation\StreamedJsonResponse`](https://github.com/roadrunner-symfony/http-bundle/blob/main/tests/App/app.php#L188)








