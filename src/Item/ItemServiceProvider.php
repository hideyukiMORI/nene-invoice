<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

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
 * Wires the Item (品目) master domain: repository, use cases, handlers, the
 * not-found exception handler, and the route registrar.
 */
final readonly class ItemServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ItemRepositoryInterface::class,
                static function (ContainerInterface $c): ItemRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoItemRepository($query, self::orgHolder($c), self::clock($c));
                },
            )
            ->set(ListItemsUseCaseInterface::class, static fn (ContainerInterface $c): ListItemsUseCase => new ListItemsUseCase(self::repository($c)))
            ->set(GetItemByIdUseCaseInterface::class, static fn (ContainerInterface $c): GetItemByIdUseCase => new GetItemByIdUseCase(self::repository($c)))
            ->set(CreateItemUseCaseInterface::class, static fn (ContainerInterface $c): CreateItemUseCase => new CreateItemUseCase(self::tx($c), self::itemsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(UpdateItemUseCaseInterface::class, static fn (ContainerInterface $c): UpdateItemUseCase => new UpdateItemUseCase(self::repository($c), self::tx($c), self::itemsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(DeleteItemUseCaseInterface::class, static fn (ContainerInterface $c): DeleteItemUseCase => new DeleteItemUseCase(self::repository($c), self::tx($c), self::itemsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(ExportItemsCsvUseCaseInterface::class, static fn (ContainerInterface $c): ExportItemsCsvUseCase => new ExportItemsCsvUseCase(self::repository($c)))
            ->set(ImportItemsCsvUseCaseInterface::class, static fn (ContainerInterface $c): ImportItemsCsvUseCase => new ImportItemsCsvUseCase(self::repository($c), self::tx($c), self::itemsFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(
                ListItemsHandler::class,
                static fn (ContainerInterface $c): ListItemsHandler => new ListItemsHandler(
                    self::listUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                GetItemByIdHandler::class,
                static fn (ContainerInterface $c): GetItemByIdHandler => new GetItemByIdHandler(
                    self::getUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                CreateItemHandler::class,
                static fn (ContainerInterface $c): CreateItemHandler => new CreateItemHandler(
                    self::createUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                UpdateItemHandler::class,
                static fn (ContainerInterface $c): UpdateItemHandler => new UpdateItemHandler(
                    self::updateUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                DeleteItemHandler::class,
                static fn (ContainerInterface $c): DeleteItemHandler => new DeleteItemHandler(
                    self::deleteUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                ExportItemsCsvHandler::class,
                static fn (ContainerInterface $c): ExportItemsCsvHandler => new ExportItemsCsvHandler(
                    self::exportUseCase($c),
                    self::psr17($c),
                ),
            )
            ->set(
                GetItemsImportTemplateHandler::class,
                static fn (ContainerInterface $c): GetItemsImportTemplateHandler => new GetItemsImportTemplateHandler(
                    self::psr17($c),
                ),
            )
            ->set(
                ImportItemsCsvHandler::class,
                static fn (ContainerInterface $c): ImportItemsCsvHandler => new ImportItemsCsvHandler(
                    self::importUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                ItemNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ItemNotFoundExceptionHandler => new ItemNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                ItemRouteRegistrar::class,
                static function (ContainerInterface $c): ItemRouteRegistrar {
                    $list = $c->get(ListItemsHandler::class);
                    $get = $c->get(GetItemByIdHandler::class);
                    $create = $c->get(CreateItemHandler::class);
                    $update = $c->get(UpdateItemHandler::class);
                    $delete = $c->get(DeleteItemHandler::class);
                    $exportCsv = $c->get(ExportItemsCsvHandler::class);
                    $importTemplate = $c->get(GetItemsImportTemplateHandler::class);
                    $importCsv = $c->get(ImportItemsCsvHandler::class);

                    if (!$list instanceof ListItemsHandler
                        || !$get instanceof GetItemByIdHandler
                        || !$create instanceof CreateItemHandler
                        || !$update instanceof UpdateItemHandler
                        || !$delete instanceof DeleteItemHandler
                        || !$exportCsv instanceof ExportItemsCsvHandler
                        || !$importTemplate instanceof GetItemsImportTemplateHandler
                        || !$importCsv instanceof ImportItemsCsvHandler
                    ) {
                        throw new LogicException('Item handler services are invalid.');
                    }

                    return new ItemRouteRegistrar($list, $get, $create, $update, $delete, $exportCsv, $importTemplate, $importCsv);
                },
            );
    }

    private static function createUseCase(ContainerInterface $c): CreateItemUseCase
    {
        $u = $c->get(CreateItemUseCaseInterface::class);

        if (!$u instanceof CreateItemUseCase) {
            throw new LogicException('Create item use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateItemUseCase
    {
        $u = $c->get(UpdateItemUseCaseInterface::class);

        if (!$u instanceof UpdateItemUseCase) {
            throw new LogicException('Update item use case service is invalid.');
        }

        return $u;
    }

    private static function deleteUseCase(ContainerInterface $c): DeleteItemUseCase
    {
        $u = $c->get(DeleteItemUseCaseInterface::class);

        if (!$u instanceof DeleteItemUseCase) {
            throw new LogicException('Delete item use case service is invalid.');
        }

        return $u;
    }

    private static function exportUseCase(ContainerInterface $c): ExportItemsCsvUseCase
    {
        $u = $c->get(ExportItemsCsvUseCaseInterface::class);

        if (!$u instanceof ExportItemsCsvUseCase) {
            throw new LogicException('Export items use case service is invalid.');
        }

        return $u;
    }

    private static function importUseCase(ContainerInterface $c): ImportItemsCsvUseCase
    {
        $u = $c->get(ImportItemsCsvUseCaseInterface::class);

        if (!$u instanceof ImportItemsCsvUseCase) {
            throw new LogicException('Import items use case service is invalid.');
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

    private static function repository(ContainerInterface $c): ItemRepositoryInterface
    {
        $repo = $c->get(ItemRepositoryInterface::class);

        if (!$repo instanceof ItemRepositoryInterface) {
            throw new LogicException('Item repository service is invalid.');
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

    /** @return Closure(DatabaseQueryExecutorInterface): ItemRepositoryInterface */
    private static function itemsFactory(ContainerInterface $c): Closure
    {
        $orgHolder = self::orgHolder($c);
        $clock     = self::clock($c);

        return static fn (DatabaseQueryExecutorInterface $exec): ItemRepositoryInterface => new PdoItemRepository($exec, $orgHolder, $clock);
    }

    private static function listUseCase(ContainerInterface $c): ListItemsUseCase
    {
        $u = $c->get(ListItemsUseCaseInterface::class);

        if (!$u instanceof ListItemsUseCase) {
            throw new LogicException('List items use case service is invalid.');
        }

        return $u;
    }

    private static function getUseCase(ContainerInterface $c): GetItemByIdUseCase
    {
        $u = $c->get(GetItemByIdUseCaseInterface::class);

        if (!$u instanceof GetItemByIdUseCase) {
            throw new LogicException('Get item use case service is invalid.');
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
