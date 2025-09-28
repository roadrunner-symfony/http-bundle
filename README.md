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

3. Configure docker-compose-roadrunner.yml/Dockerfile/.rr.http.yaml/config/packages/roadrunner.http.yaml




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








