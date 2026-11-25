<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler;

use LogicException;
use Roadrunner\Integration\Symfony\Http\Command\DebugMiddlewareCommand;
use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\RouterInterface;

final class ScopedMiddlewareCompilerPass implements CompilerPass
{
    public const COMMAND_ID = 'roadrunner.http.debug_middleware';
    public const TAG        = 'roadrunner.http.middleware';

    public function process(ContainerBuilder $container): void
    {
        $services = [];

        foreach ($container->findTaggedServiceIds(self::TAG, true) as $id => $_) {
            $class = $container->getDefinition($id)->getClass();

            if (null === $class || !is_a($class, Middleware::class, true)) {
                throw new LogicException(sprintf('Service "%s" tagged "%s" must declare a class implementing %s.', $id, self::TAG, Middleware::class));
            }

            $services[$id] = new Reference($id);
        }

        // Symfony has already resolved service inheritance, decoration and alias chains.
        $aliases = $container->getAliases();

        foreach ($aliases as $id => $alias) {
            $target = (string) $alias;

            if (isset($services[$target])) {
                $services[$id] = $services[$target];
            }
        }

        $container->getDefinition('roadrunner.http.scoped_resolver')
            ->setArgument(0, ServiceLocatorTagPass::register($container, $services))
        ;
        $container->log($this, sprintf('[FIX] Registered %d scoped middleware IDs from optimized service definitions.', count($services)));

        if (!$container->has('router') || !interface_exists(RouterInterface::class) || !class_exists(Command::class)) {
            return;
        }

        $controllerClasses = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!$definition->isAbstract() && null !== $class) {
                $controllerClasses[$id] = $class;
            }
        }

        foreach ($aliases as $id => $alias) {
            $target = (string) $alias;

            if (isset($controllerClasses[$target])) {
                $controllerClasses[$id] = $controllerClasses[$target];
            }
        }

        $container->register(self::COMMAND_ID, DebugMiddlewareCommand::class)
            ->setArguments([
                new Reference((string) ($aliases['router'] ?? 'router')),
                new Reference('roadrunner.http.scoped_resolver'),
                $container->getDefinition('roadrunner.http.pipeline_middleware')->getArgument(2),
                $controllerClasses,
            ])
            ->addTag('console.command', ['command' => 'debug:middleware'])
        ;
    }
}
