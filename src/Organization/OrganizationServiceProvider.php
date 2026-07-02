<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Audit\AuditServiceProvider;
use Psr\Container\ContainerInterface;

/**
 * Wires the Organization (tenant) domain: repository, use cases, handlers,
 * domain exception handlers, and the route registrar.
 */
final readonly class OrganizationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                OrganizationRepositoryInterface::class,
                static function (ContainerInterface $container): OrganizationRepositoryInterface {
                    $query = $container->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoOrganizationRepository($query);
                },
            )
            ->set(ListOrganizationsUseCaseInterface::class, static fn (ContainerInterface $c): ListOrganizationsUseCase => new ListOrganizationsUseCase(self::repository($c)))
            ->set(GetOrganizationByIdUseCaseInterface::class, static fn (ContainerInterface $c): GetOrganizationByIdUseCase => new GetOrganizationByIdUseCase(self::repository($c)))
            ->set(CreateOrganizationUseCaseInterface::class, static fn (ContainerInterface $c): CreateOrganizationUseCase => new CreateOrganizationUseCase(self::tx($c), self::organizationsFactory(), AuditServiceProvider::recorderFactory($c), self::initialAdminFactory()))
            ->set(UpdateOrganizationUseCaseInterface::class, static fn (ContainerInterface $c): UpdateOrganizationUseCase => new UpdateOrganizationUseCase(self::repository($c), self::tx($c), self::organizationsFactory(), AuditServiceProvider::recorderFactory($c)))
            ->set(DeleteOrganizationUseCaseInterface::class, static fn (ContainerInterface $c): DeleteOrganizationUseCase => new DeleteOrganizationUseCase(self::repository($c), self::tx($c), self::organizationsFactory(), AuditServiceProvider::recorderFactory($c)))
            ->set(
                ListOrganizationsHandler::class,
                static fn (ContainerInterface $c): ListOrganizationsHandler => new ListOrganizationsHandler(
                    self::listUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                GetOrganizationByIdHandler::class,
                static fn (ContainerInterface $c): GetOrganizationByIdHandler => new GetOrganizationByIdHandler(
                    self::getUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                CreateOrganizationHandler::class,
                static fn (ContainerInterface $c): CreateOrganizationHandler => new CreateOrganizationHandler(
                    self::createUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                UpdateOrganizationHandler::class,
                static fn (ContainerInterface $c): UpdateOrganizationHandler => new UpdateOrganizationHandler(
                    self::updateUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                DeleteOrganizationHandler::class,
                static fn (ContainerInterface $c): DeleteOrganizationHandler => new DeleteOrganizationHandler(
                    self::deleteUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                OrganizationNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): OrganizationNotFoundExceptionHandler => new OrganizationNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                OrganizationSlugConflictExceptionHandler::class,
                static fn (ContainerInterface $c): OrganizationSlugConflictExceptionHandler => new OrganizationSlugConflictExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                OrganizationRouteRegistrar::class,
                static function (ContainerInterface $c): OrganizationRouteRegistrar {
                    $list = $c->get(ListOrganizationsHandler::class);
                    $get = $c->get(GetOrganizationByIdHandler::class);
                    $create = $c->get(CreateOrganizationHandler::class);
                    $update = $c->get(UpdateOrganizationHandler::class);
                    $delete = $c->get(DeleteOrganizationHandler::class);

                    if (!$list instanceof ListOrganizationsHandler
                        || !$get instanceof GetOrganizationByIdHandler
                        || !$create instanceof CreateOrganizationHandler
                        || !$update instanceof UpdateOrganizationHandler
                        || !$delete instanceof DeleteOrganizationHandler
                    ) {
                        throw new LogicException('Organization handler services are invalid.');
                    }

                    return new OrganizationRouteRegistrar($list, $get, $create, $update, $delete);
                },
            );
    }

    private static function repository(ContainerInterface $c): OrganizationRepositoryInterface
    {
        $repo = $c->get(OrganizationRepositoryInterface::class);

        if (!$repo instanceof OrganizationRepositoryInterface) {
            throw new LogicException('Organization repository service is invalid.');
        }

        return $repo;
    }

    private static function tx(ContainerInterface $c): DatabaseTransactionManagerInterface
    {
        $tx = $c->get(DatabaseTransactionManagerInterface::class);

        if (!$tx instanceof DatabaseTransactionManagerInterface) {
            throw new LogicException('Transaction manager service is invalid.');
        }

        return $tx;
    }

    /** @return Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface */
    private static function organizationsFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $exec): OrganizationRepositoryInterface => new PdoOrganizationRepository($exec);
    }

    /** @return Closure(DatabaseQueryExecutorInterface): InitialAdminRepositoryInterface */
    private static function initialAdminFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $exec): InitialAdminRepositoryInterface => new PdoInitialAdminRepository($exec);
    }

    private static function listUseCase(ContainerInterface $c): ListOrganizationsUseCase
    {
        $u = $c->get(ListOrganizationsUseCaseInterface::class);

        if (!$u instanceof ListOrganizationsUseCase) {
            throw new LogicException('List organizations use case service is invalid.');
        }

        return $u;
    }

    private static function getUseCase(ContainerInterface $c): GetOrganizationByIdUseCase
    {
        $u = $c->get(GetOrganizationByIdUseCaseInterface::class);

        if (!$u instanceof GetOrganizationByIdUseCase) {
            throw new LogicException('Get organization use case service is invalid.');
        }

        return $u;
    }

    private static function createUseCase(ContainerInterface $c): CreateOrganizationUseCase
    {
        $u = $c->get(CreateOrganizationUseCaseInterface::class);

        if (!$u instanceof CreateOrganizationUseCase) {
            throw new LogicException('Create organization use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateOrganizationUseCase
    {
        $u = $c->get(UpdateOrganizationUseCaseInterface::class);

        if (!$u instanceof UpdateOrganizationUseCase) {
            throw new LogicException('Update organization use case service is invalid.');
        }

        return $u;
    }

    private static function deleteUseCase(ContainerInterface $c): DeleteOrganizationUseCase
    {
        $u = $c->get(DeleteOrganizationUseCaseInterface::class);

        if (!$u instanceof DeleteOrganizationUseCase) {
            throw new LogicException('Delete organization use case service is invalid.');
        }

        return $u;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        $j = $c->get(JsonResponseFactory::class);

        if (!$j instanceof JsonResponseFactory) {
            throw new LogicException('JSON response factory service is invalid.');
        }

        return $j;
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        $p = $c->get(ProblemDetailsResponseFactory::class);

        if (!$p instanceof ProblemDetailsResponseFactory) {
            throw new LogicException('Problem details response factory service is invalid.');
        }

        return $p;
    }
}
