<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler;

use Roadrunner\Integration\Symfony\Http\Bridge\VarDumper\DumpMiddleware;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Configuration;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\RoadRunnerHttpExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type RawConfiguration from Configuration
 */
final class VarDumperCompilerPass implements CompilerPass
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('debug.dump_listener')) {
            return;
        }

        $container->register('roadrunner.http.dump.middleware', DumpMiddleware::class)
            ->setArguments([
                new Reference('debug.dump_listener'),
            ])
        ;

        /** @var RawConfiguration $config */
        $config                  = $container->getParameter(RoadRunnerHttpExtension::CONFIG_NAME);
        $config['middlewares'][] = 'roadrunner.http.dump.middleware';

        $container->setParameter(RoadRunnerHttpExtension::CONFIG_NAME, $config);
    }
}
