<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2025, The RoadRunner community
 */

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Monolog\Logger;
use Roadrunner\Integration\Symfony\Http\Bridge\Doctrine\ClearEntityManagerMiddleware;
use Roadrunner\Integration\Symfony\Http\DataCollector\RoadRunnerHttpCollector;
use Roadrunner\Integration\Symfony\Http\InstalledVersions;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareResolver;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator, ContainerBuilder $builder): void {
    $services = $configurator->services();

    $services->set('roadrunner.http.scoped_resolver', ScopedMiddlewareResolver::class);

    $services->set('roadrunner.http.scoped_subscriber', ScopedMiddlewareSubscriber::class)
        ->args([
            service('roadrunner.http.scoped_resolver'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services->set('roadrunner.http.pipeline_middleware', PipelineMiddleware::class)
        ->args([
            service('kernel'),
            [],
        ])
    ;


    $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? [];

    if (is_array($bundles) && isset($bundles['WebProfilerBundle'])) {
        $services->set(RoadRunnerHttpCollector::SERVICE_ID, RoadRunnerHttpCollector::class)
            ->args([service('request_stack')])
            ->tag('data_collector', [
                'id'       => RoadRunnerHttpCollector::NAME,
                'template' => '@RoadRunnerHttp/data_collector/roadrunner_http.html.twig',
            ])
        ;
    }

    if (InstalledVersions::willBeAvailable('symfony/monolog-bundle', Logger::class)) {
        $services->set('monolog.logger.roadrunner.http')
            ->parent('monolog.logger')
            ->call('withName', ['roadrunner.http'], true)
        ;
    }


    if (InstalledVersions::willBeAvailable('doctrine/doctrine-bundle', EntityManager::class)) {
        $services->set('roadrunner.http.doctrine_clear_entity_manager.middleware', ClearEntityManagerMiddleware::class)
            ->args([service('doctrine')])
        ;
    }
};
