<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use NeneInvoice\Auth\RefreshTokenIssuer;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the disposable-demo domain: the data seeder, the HTTP handler, and its
 * route registrar. All dependencies (org creation, refresh-token issuance, PDO
 * connection factory) are reused from existing providers — no auth code is added.
 */
final readonly class DemoServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DemoDataSeeder::class,
                static function (ContainerInterface $c): DemoDataSeeder {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);
                    $clock = $c->get(ClockInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    if (!$clock instanceof ClockInterface) {
                        throw new LogicException('Clock service is invalid.');
                    }

                    return new DemoDataSeeder($query, $clock);
                },
            )
            ->set(
                StartDemoHandler::class,
                static function (ContainerInterface $c): StartDemoHandler {
                    $createOrg = $c->get(CreateOrganizationUseCaseInterface::class);
                    $query = $c->get(DatabaseQueryExecutorInterface::class);
                    $seeder = $c->get(DemoDataSeeder::class);
                    $refreshTokenIssuer = $c->get(RefreshTokenIssuer::class);
                    $problemDetails = $c->get(ProblemDetailsResponseFactory::class);
                    $psr17 = $c->get(Psr17Factory::class);

                    if (!$createOrg instanceof CreateOrganizationUseCaseInterface) {
                        throw new LogicException('Create organization use case service is invalid.');
                    }

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    if (!$seeder instanceof DemoDataSeeder) {
                        throw new LogicException('Demo data seeder service is invalid.');
                    }

                    if (!$refreshTokenIssuer instanceof RefreshTokenIssuer) {
                        throw new LogicException('Refresh token issuer service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    return new StartDemoHandler($createOrg, $query, $seeder, $refreshTokenIssuer, $problemDetails, $psr17);
                },
            )
            ->set(
                DemoRouteRegistrar::class,
                static function (ContainerInterface $c): DemoRouteRegistrar {
                    $handler = $c->get(StartDemoHandler::class);

                    if (!$handler instanceof StartDemoHandler) {
                        throw new LogicException('Start demo handler service is invalid.');
                    }

                    return new DemoRouteRegistrar($handler);
                },
            );
    }
}
