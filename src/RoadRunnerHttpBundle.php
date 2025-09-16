<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http;

use Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler\DoctrineCompilerPass;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler\PipelineMiddlewareCompilerPass;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler\RunnerCompilerPass;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler\SentryCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RoadRunnerHttpBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RunnerCompilerPass());
        $container->addCompilerPass(new DoctrineCompilerPass());
        $container->addCompilerPass(new SentryCompilerPass());
        $container->addCompilerPass(new PipelineMiddlewareCompilerPass());
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
