<?php

declare(strict_types=1);

namespace Roadrunner\Integration\Symfony\Http\Bridge\Doctrine;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface as Logger;
use Roadrunner\Integration\Symfony\Http\Middleware\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConnectionPingMiddleware implements Middleware
{
    /**
     * @param non-empty-string          $entityManagerName
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly string $entityManagerName,
        private readonly ?Logger $logger = null,
    ) {}


    public function process(Request $request, callable $next): Response
    {
        try {
            $entityManager = $this->managerRegistry->getManager($this->entityManagerName);
        } catch (InvalidArgumentException $e) {
            $this->logger?->error(sprintf('Failed to initialize Doctrine Ping connection, reason: %s', $e->getMessage()), [
                'e' => $e,
            ]);


            return $next($request);
        }

        if (!$entityManager instanceof EntityManager) {
            $this->logger?->error(
                sprintf('Failed to initialize Doctrine Ping connection, reason: %s', 'Entity Manager must be an instance of Doctrine\ORM\EntityManagerInterface')
            );


            return $next($request);
        }

        $connection = $entityManager->getConnection();

        try {
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (DBALException) {
            $connection->close();
        }

        if (!$entityManager->isOpen()) {
            $this->managerRegistry->resetManager($this->entityManagerName);
        }

        return $next($request);
    }
}
