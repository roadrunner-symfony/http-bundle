<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Monolog\Logger;
use Roadrunner\Integration\Symfony\Http\Bridge\Doctrine\ClearEntityManagerMiddleware;
use Roadrunner\Integration\Symfony\Http\InstalledVersions;
use Roadrunner\Integration\Symfony\Http\Middleware\PipelineMiddleware;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set('roadrunner.http.pipeline_middleware', PipelineMiddleware::class)
        ->args([
            service('kernel'),
            [],
        ])
    ;


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
