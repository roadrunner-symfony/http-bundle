<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Runtime;

use LogicException;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Symfony\Component\Runtime\RunnerInterface as Runner;
use Symfony\Component\Runtime\SymfonyRuntime;

final class HttpRuntime extends SymfonyRuntime
{
    public function getRunner(?object $application): Runner
    {
        if ($application instanceof Kernel) {
            $application->boot();

            $runner = $application->getContainer()->get('roadrunner.http.runner');

            if ($runner instanceof HttpRunner) {
                return $runner;
            }

        }

        throw new LogicException('Is not a http roadrunner runtime');
    }
}
