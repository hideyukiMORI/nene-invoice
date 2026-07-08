<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the Client (取引先) domain: repository, read use cases, handlers, the
 * not-found exception handler, and the route registrar.
 */
final readonly class ClientServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ClientRepositoryInterface::class,
                static function (ContainerInterface $c): ClientRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoClientRepository($query, self::orgHolder($c), self::clock($c));
                },
            )
            ->set(ListClientsUseCaseInterface::class, static fn (ContainerInterface $c): ListClientsUseCase => new ListClientsUseCase(self::repository($c)))
            ->set(GetClientByIdUseCaseInterface::class, static fn (ContainerInterface $c): GetClientByIdUseCase => new GetClientByIdUseCase(self::repository($c)))
            ->set(CreateClientUseCaseInterface::class, static fn (ContainerInterface $c): CreateClientUseCase => new CreateClientUseCase(self::tx($c), self::clientsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(UpdateClientUseCaseInterface::class, static fn (ContainerInterface $c): UpdateClientUseCase => new UpdateClientUseCase(self::repository($c), self::tx($c), self::clientsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(DeleteClientUseCaseInterface::class, static fn (ContainerInterface $c): DeleteClientUseCase => new DeleteClientUseCase(self::repository($c), self::tx($c), self::clientsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(ExportClientsCsvUseCaseInterface::class, static fn (ContainerInterface $c): ExportClientsCsvUseCase => new ExportClientsCsvUseCase(self::repository($c)))
            ->set(ImportClientsCsvUseCaseInterface::class, static fn (ContainerInterface $c): ImportClientsCsvUseCase => new ImportClientsCsvUseCase(self::repository($c), self::tx($c), self::clientsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(
                ListClientsHandler::class,
                static fn (ContainerInterface $c): ListClientsHandler => new ListClientsHandler(
                    self::listUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                GetClientByIdHandler::class,
                static fn (ContainerInterface $c): GetClientByIdHandler => new GetClientByIdHandler(
                    self::getUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                CreateClientHandler::class,
                static fn (ContainerInterface $c): CreateClientHandler => new CreateClientHandler(
                    self::createUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                UpdateClientHandler::class,
                static fn (ContainerInterface $c): UpdateClientHandler => new UpdateClientHandler(
                    self::updateUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                DeleteClientHandler::class,
                static fn (ContainerInterface $c): DeleteClientHandler => new DeleteClientHandler(
                    self::deleteUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                ExportClientsCsvHandler::class,
                static fn (ContainerInterface $c): ExportClientsCsvHandler => new ExportClientsCsvHandler(
                    self::exportUseCase($c),
                    self::psr17($c),
                ),
            )
            ->set(
                GetClientsImportTemplateHandler::class,
                static fn (ContainerInterface $c): GetClientsImportTemplateHandler => new GetClientsImportTemplateHandler(
                    self::psr17($c),
                ),
            )
            ->set(
                ImportClientsCsvHandler::class,
                static fn (ContainerInterface $c): ImportClientsCsvHandler => new ImportClientsCsvHandler(
                    self::importUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                ClientNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ClientNotFoundExceptionHandler => new ClientNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                InvalidRegistrationNumberExceptionHandler::class,
                static fn (ContainerInterface $c): InvalidRegistrationNumberExceptionHandler => new InvalidRegistrationNumberExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                ClientRouteRegistrar::class,
                static function (ContainerInterface $c): ClientRouteRegistrar {
                    $list = $c->get(ListClientsHandler::class);
                    $get = $c->get(GetClientByIdHandler::class);
                    $create = $c->get(CreateClientHandler::class);
                    $update = $c->get(UpdateClientHandler::class);
                    $delete = $c->get(DeleteClientHandler::class);
                    $exportCsv = $c->get(ExportClientsCsvHandler::class);
                    $importTemplate = $c->get(GetClientsImportTemplateHandler::class);
                    $importCsv = $c->get(ImportClientsCsvHandler::class);

                    if (!$list instanceof ListClientsHandler
                        || !$get instanceof GetClientByIdHandler
                        || !$create instanceof CreateClientHandler
                        || !$update instanceof UpdateClientHandler
                        || !$delete instanceof DeleteClientHandler
                        || !$exportCsv instanceof ExportClientsCsvHandler
                        || !$importTemplate instanceof GetClientsImportTemplateHandler
                        || !$importCsv instanceof ImportClientsCsvHandler
                    ) {
                        throw new LogicException('Client handler services are invalid.');
                    }

                    return new ClientRouteRegistrar($list, $get, $create, $update, $delete, $exportCsv, $importTemplate, $importCsv);
                },
            );
    }

    private static function createUseCase(ContainerInterface $c): CreateClientUseCase
    {
        $u = $c->get(CreateClientUseCaseInterface::class);

        if (!$u instanceof CreateClientUseCase) {
            throw new LogicException('Create client use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateClientUseCase
    {
        $u = $c->get(UpdateClientUseCaseInterface::class);

        if (!$u instanceof UpdateClientUseCase) {
            throw new LogicException('Update client use case service is invalid.');
        }

        return $u;
    }

    private static function deleteUseCase(ContainerInterface $c): DeleteClientUseCase
    {
        $u = $c->get(DeleteClientUseCaseInterface::class);

        if (!$u instanceof DeleteClientUseCase) {
            throw new LogicException('Delete client use case service is invalid.');
        }

        return $u;
    }

    private static function exportUseCase(ContainerInterface $c): ExportClientsCsvUseCase
    {
        $u = $c->get(ExportClientsCsvUseCaseInterface::class);

        if (!$u instanceof ExportClientsCsvUseCase) {
            throw new LogicException('Export clients use case service is invalid.');
        }

        return $u;
    }

    private static function importUseCase(ContainerInterface $c): ImportClientsCsvUseCase
    {
        $u = $c->get(ImportClientsCsvUseCaseInterface::class);

        if (!$u instanceof ImportClientsCsvUseCase) {
            throw new LogicException('Import clients use case service is invalid.');
        }

        return $u;
    }

    private static function psr17(ContainerInterface $c): Psr17Factory
    {
        $p = $c->get(Psr17Factory::class);

        if (!$p instanceof Psr17Factory) {
            throw new LogicException('PSR-17 factory service is invalid.');
        }

        return $p;
    }

    private static function repository(ContainerInterface $c): ClientRepositoryInterface
    {
        $repo = $c->get(ClientRepositoryInterface::class);

        if (!$repo instanceof ClientRepositoryInterface) {
            throw new LogicException('Client repository service is invalid.');
        }

        return $repo;
    }

    /** @return RequestScopedHolder<int> */
    private static function orgHolder(ContainerInterface $c): RequestScopedHolder
    {
        $holder = $c->get(ApplicationServiceProvider::ORG_ID_HOLDER);

        if (!$holder instanceof RequestScopedHolder) {
            throw new LogicException('Org id holder service is invalid.');
        }

        return $holder;
    }

    private static function tx(ContainerInterface $c): DatabaseTransactionManagerInterface
    {
        $tx = $c->get(DatabaseTransactionManagerInterface::class);

        if (!$tx instanceof DatabaseTransactionManagerInterface) {
            throw new LogicException('Transaction manager service is invalid.');
        }

        return $tx;
    }

    /** @return Closure(DatabaseQueryExecutorInterface): ClientRepositoryInterface */
    private static function clientsFactory(ContainerInterface $c): Closure
    {
        $orgHolder = self::orgHolder($c);
        $clock     = self::clock($c);

        return static fn (DatabaseQueryExecutorInterface $exec): ClientRepositoryInterface => new PdoClientRepository($exec, $orgHolder, $clock);
    }

    private static function listUseCase(ContainerInterface $c): ListClientsUseCase
    {
        $u = $c->get(ListClientsUseCaseInterface::class);

        if (!$u instanceof ListClientsUseCase) {
            throw new LogicException('List clients use case service is invalid.');
        }

        return $u;
    }

    private static function getUseCase(ContainerInterface $c): GetClientByIdUseCase
    {
        $u = $c->get(GetClientByIdUseCaseInterface::class);

        if (!$u instanceof GetClientByIdUseCase) {
            throw new LogicException('Get client use case service is invalid.');
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

    private static function clock(ContainerInterface $c): ClockInterface
    {
        $clock = $c->get(ClockInterface::class);

        if (!$clock instanceof ClockInterface) {
            throw new LogicException('Clock service is invalid.');
        }

        return $clock;
    }
}
