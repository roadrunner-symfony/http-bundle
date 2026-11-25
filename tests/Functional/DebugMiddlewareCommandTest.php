<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Test\Functional;

use PHPUnit\Framework\TestCase;
use Roadrunner\Integration\Symfony\Http\Command\DebugMiddlewareCommand;
use Roadrunner\Integration\Symfony\Http\DependencyInjection\Compiler\ScopedMiddlewareCompilerPass;
use Roadrunner\Integration\Symfony\Http\Middleware\Attribute\UseMiddleware;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareResolver;
use Roadrunner\Integration\Symfony\Http\Test\App\MiddlewareController;
use Roadrunner\Integration\Symfony\Http\Test\App\MiddlewareFixture;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class DebugMiddlewareCommandTest extends TestCase
{
    public function testListsLocalizedAndAliasedBindings(): void
    {
        $routes = new RouteCollection();
        $routes->add('preview.fr', new Route('/fr/preview', ['_controller' => 'example', '_canonical_route' => 'preview']));
        $routes->add('base', new Route('/base', ['_controller' => '\example::__INVOKE']));
        $route = 'preview.fr';

        if (method_exists($routes, 'addAlias')) {
            $routes->addAlias('preview_alias', $route);
            $route = 'preview_alias';
        }

        $command = $this->command($routes);

        self::assertSame(Command::SUCCESS, $command->execute(['route' => $route]));
        self::assertStringContainsString('route_middleware', $command->getDisplay());
        self::assertStringContainsString('compiled_global', $command->getDisplay());

        self::assertSame(Command::SUCCESS, $command->execute(['route' => 'base']));
        self::assertStringNotContainsString('route_middleware', $command->getDisplay());

        self::assertSame(Command::FAILURE, $command->execute(['route' => 'unknown']));
        self::assertStringContainsString('Unknown route "unknown"', $command->getDisplay());
    }

    public function testKeepsNamesDistinctWhenRoutesShareAnInstance(): void
    {
        $routes = new RouteCollection();
        $route  = new Route('/shared', ['_controller' => 'example']);
        $routes->add('preview', $route);
        $routes->add('base', $route);

        $selected = 'preview';

        if (method_exists($routes, 'addAlias')) {
            $routes->addAlias('details', 'preview');
            $routes->addAlias('shared_preview', 'details');
            $selected = 'shared_preview';
        }

        $command = $this->command($routes);

        self::assertSame(Command::SUCCESS, $command->execute(['route' => $selected]));
        self::assertStringContainsString($selected, $command->getDisplay());
        self::assertStringContainsString('route_middleware', $command->getDisplay());

        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertMatchesRegularExpression('/preview[^\n]+route_middleware/', $command->getDisplay());
        self::assertDoesNotMatchRegularExpression('/base[^\n]+route_middleware/', $command->getDisplay());
    }

    public function testReportsUnknownSelectorAndDynamicController(): void
    {
        $routes = new RouteCollection();
        $routes->add('base', new Route('/base', ['_controller' => 'example']));
        $routes->add('dynamic', new Route('/dynamic', ['_controller' => 'unresolvable_service']));

        $command = $this->command($routes);

        self::assertSame(Command::FAILURE, $command->execute([]));
        self::assertStringContainsString('Invalid middleware route selector "preview"', $command->getDisplay());
        self::assertStringContainsString('controller cannot be resolved statically', $command->getDisplay());
    }

    public function testUsesCompiledDefinitionsForMiddlewareAndControllers(): void
    {
        $routes = new RouteCollection();
        $routes->add('preview', new Route('/preview', ['_controller' => 'example']));
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $container = new ContainerBuilder();
        $container->register('router.test', RouterInterface::class)->setSynthetic(true)->setPublic(true);
        $container->setAlias('router', 'router.test');

        $container->register('example', MiddlewareController::class);
        $container->register('decorator', self::class)->setDecoratedService('example');

        $container->register('middleware.parent', MiddlewareFixture::class)->setArguments(['route'])->setAbstract(true);
        $container->setAlias('middleware.parent_alias', 'middleware.parent');
        $container->setDefinition('route_middleware', (new ChildDefinition('middleware.parent_alias'))->addTag(ScopedMiddlewareCompilerPass::TAG));

        $container->register('roadrunner.http.scoped_resolver', ScopedMiddlewareResolver::class)->setPublic(true);
        $container->register('roadrunner.http.pipeline_middleware')->setAbstract(true)->setArguments([null, [], ['compiled_global']]);

        $container->addCompilerPass(new ScopedMiddlewareCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING, 10);
        $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);

        $container->compile();
        $container->set('router.test', $router);

        $resolver = $container->get('roadrunner.http.scoped_resolver');
        self::assertInstanceOf(ScopedMiddlewareResolver::class, $resolver);
        self::assertInstanceOf(MiddlewareFixture::class, $resolver->get('route_middleware'));

        $loader = $container->get('console.command_loader');
        self::assertInstanceOf(CommandLoaderInterface::class, $loader);
        $command = new CommandTester($loader->get('debug:middleware'));

        self::assertSame(Command::SUCCESS, $command->execute(['route' => 'preview']));
        self::assertStringContainsString('route_middleware', $command->getDisplay());
        self::assertStringContainsString('compiled_global', $command->getDisplay());
    }

    #[UseMiddleware('route_middleware', route: 'preview')]
    public function __invoke(): void {}

    private function command(RouteCollection $routes): CommandTester
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);
        $resolver = new ScopedMiddlewareResolver(new ServiceLocator(['route_middleware' => static fn(): mixed => null]));

        return new CommandTester(new DebugMiddlewareCommand($router, $resolver, ['compiled_global'], ['example' => self::class]));
    }
}
