<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditRecorderInterface;
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

                    return new PdoItemRepository($query, self::orgHolder($c));
                },
            )
            ->set(ListItemsUseCase::class, static fn (ContainerInterface $c): ListItemsUseCase => new ListItemsUseCase(self::repository($c)))
            ->set(GetItemByIdUseCase::class, static fn (ContainerInterface $c): GetItemByIdUseCase => new GetItemByIdUseCase(self::repository($c)))
            ->set(CreateItemUseCase::class, static fn (ContainerInterface $c): CreateItemUseCase => new CreateItemUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
            ->set(UpdateItemUseCase::class, static fn (ContainerInterface $c): UpdateItemUseCase => new UpdateItemUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
            ->set(DeleteItemUseCase::class, static fn (ContainerInterface $c): DeleteItemUseCase => new DeleteItemUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
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

                    if (!$list instanceof ListItemsHandler
                        || !$get instanceof GetItemByIdHandler
                        || !$create instanceof CreateItemHandler
                        || !$update instanceof UpdateItemHandler
                        || !$delete instanceof DeleteItemHandler
                    ) {
                        throw new LogicException('Item handler services are invalid.');
                    }

                    return new ItemRouteRegistrar($list, $get, $create, $update, $delete);
                },
            );
    }

    private static function createUseCase(ContainerInterface $c): CreateItemUseCase
    {
        $u = $c->get(CreateItemUseCase::class);

        if (!$u instanceof CreateItemUseCase) {
            throw new LogicException('Create item use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateItemUseCase
    {
        $u = $c->get(UpdateItemUseCase::class);

        if (!$u instanceof UpdateItemUseCase) {
            throw new LogicException('Update item use case service is invalid.');
        }

        return $u;
    }

    private static function deleteUseCase(ContainerInterface $c): DeleteItemUseCase
    {
        $u = $c->get(DeleteItemUseCase::class);

        if (!$u instanceof DeleteItemUseCase) {
            throw new LogicException('Delete item use case service is invalid.');
        }

        return $u;
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

    private static function audit(ContainerInterface $c): AuditRecorderInterface
    {
        $recorder = $c->get(AuditRecorderInterface::class);

        if (!$recorder instanceof AuditRecorderInterface) {
            throw new LogicException('Audit recorder service is invalid.');
        }

        return $recorder;
    }

    private static function listUseCase(ContainerInterface $c): ListItemsUseCase
    {
        $u = $c->get(ListItemsUseCase::class);

        if (!$u instanceof ListItemsUseCase) {
            throw new LogicException('List items use case service is invalid.');
        }

        return $u;
    }

    private static function getUseCase(ContainerInterface $c): GetItemByIdUseCase
    {
        $u = $c->get(GetItemByIdUseCase::class);

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
}
