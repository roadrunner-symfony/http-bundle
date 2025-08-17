<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler;

use Roadrunner\Integration\Symfony\Http\DependencyInjection\Configuration;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\HttpExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type RawConfiguration from Configuration
 */
final class PipelineMiddlewareCompilerPass implements CompilerPass
{
    public function process(ContainerBuilder $container): void
    {
        $middlewares = array_map(
            static fn(string $id): Reference => new Reference($id),
            $container->getParameter(HttpExtension::CONFIG_NAME)['middlewares']
        );

        $container->getDefinition('roadrunner.http.pipeline_middleware')
            ->replaceArgument(1, $middlewares)
        ;
    }
}
