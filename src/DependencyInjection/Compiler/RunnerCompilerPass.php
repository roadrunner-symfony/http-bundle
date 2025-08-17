<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler;

use Roadrunner\Integration\Symfony\Http\DependencyInjection\Configuration;

use function Roadrunner\Integration\Symfony\Http\DependencyInjection\definition;

use Roadrunner\Integration\Symfony\Http\Runtime\HttpRunner;
use Roadrunner\Integration\Symfony\Http\Runtime\HttpWorker;
use Spiral\RoadRunner\Http\HttpWorker as RoadRunnerHttpWorker;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type RawConfiguration from Configuration
 */
final class RunnerCompilerPass implements CompilerPass
{
    public function process(ContainerBuilder $container): void
    {
        /** @var RawConfiguration $config */
        $config = $container->getParameter('roadrunner.http.config');

        $factoryArguments = [];

        if ($container->hasDefinition($config['logger'])) {
            $factoryArguments = [
                new Reference($config['logger']),
            ];
        }


        $httpWorkerRoadRunner = definition(RoadRunnerHttpWorker::class)
            ->setArguments([
                definition(RoadRunnerWorker::class)
                    ->setFactory([RoadRunnerWorker::class, 'create'])
                    ->setArguments($factoryArguments),
            ])
        ;

        $container->register('roadrunner.http.runner', HttpRunner::class)
            ->setArguments([
                new Reference('kernel'),
                new Definition(HttpWorker::class, [new Reference('error_renderer'), $httpWorkerRoadRunner]),
                new Reference('roadrunner.http.pipeline_middleware'),
            ])
            ->setPublic(true)
        ;
    }
}
