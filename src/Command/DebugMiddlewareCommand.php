<?php

/**
 * Symfony RoadRunner Http
 *
 * @author    Vlad Shashkov <shashkov.root@gmail.com>
 * @copyright Copyright (c) 2026, The RoadRunner community
 */

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Command;

use LogicException;
use Roadrunner\Integration\Symfony\Http\Middleware\ScopedMiddlewareResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

final class DebugMiddlewareCommand extends Command
{
    /**
     * @param list<string> $globalIds
     * @param array<string, string> $controllerClasses
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ScopedMiddlewareResolver $resolver,
        private readonly array $globalIds,
        private readonly array $controllerClasses,
    ) {
        parent::__construct('debug:middleware');
    }

    protected function configure(): void
    {
        $this->setDescription('Shows the configured RoadRunner middleware chain for Symfony routes.')
            ->addArgument('route', InputArgument::OPTIONAL, 'Exact route name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Cloning gives each registered name its own Route object for the identity lookup.
        $io            = new SymfonyStyle($input, $output);
        $collection    = clone $this->router->getRouteCollection();
        $selected      = $input->getArgument('route');
        $selectedRoute = is_string($selected) ? $collection->get($selected) : null;

        if (is_string($selected) && null === $selectedRoute) {
            $io->error(sprintf('Unknown route "%s".', $selected));

            return self::FAILURE;
        }

        $routes        = $collection->all();
        $targets       = [];
        $namesByAction = [];

        foreach ($routes as $name => $route) {
            $target                         = $this->resolver->describeController($route->getDefault('_controller'), $this->controllerClasses);
            $canonical                      = $route->getDefault('_canonical_route');
            $canonical                      = is_string($canonical) ? $canonical : $name;
            $targets[spl_object_id($route)] = [$target, $canonical];

            if (null !== $target) {
                $namesByAction[strtolower($target[0] . '::' . $target[1])][] = $canonical;
            }
        }

        $display = null === $selectedRoute ? $routes : [$selected => $selectedRoute];
        $rows    = [];
        $failed  = false;

        foreach ($display as $name => $route) {
            foreach ($this->globalIds as $id) {
                $rows[] = [$name, $id, 'global', ''];
            }

            [$target, $canonical] = $targets[spl_object_id($route)];

            if (null === $target) {
                $rows[] = [$name, '(controller cannot be resolved statically)', '', ''];

                continue;
            }

            [$class, $method] = $target;

            try {
                $bindings = $this->resolver->resolve(
                    $class,
                    $method,
                    $canonical,
                    array_values(array_unique($namesByAction[strtolower($class . '::' . $method)])),
                );
            } catch (LogicException $error) {
                $io->error($error->getMessage());
                $failed = true;

                continue;
            }

            foreach ($bindings as $binding) {
                $rows[] = [$name, $binding['id'], $binding['scope'], (string) $binding['priority']];
            }

            if ([] === $bindings && [] === $this->globalIds) {
                $rows[] = [$name, '(no middleware)', '', ''];
            }
        }

        $io->table(['Route', 'Service ID', 'Scope', 'Priority'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
