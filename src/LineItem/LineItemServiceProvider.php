<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Item\ItemRepositoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the shared line-item repository and the history-based suggestion
 * endpoint (#315).
 */
final readonly class LineItemServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                LineItemRepositoryInterface::class,
                static function (ContainerInterface $c): LineItemRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoLineItemRepository($query, self::orgHolder($c));
                },
            )
            ->set(
                ListLineItemSuggestionsUseCaseInterface::class,
                static fn (ContainerInterface $c): ListLineItemSuggestionsUseCase => new ListLineItemSuggestionsUseCase(
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, ItemRepositoryInterface::class),
                ),
            )
            ->set(
                ListLineItemSuggestionsHandler::class,
                static fn (ContainerInterface $c): ListLineItemSuggestionsHandler => new ListLineItemSuggestionsHandler(
                    self::resolve($c, ListLineItemSuggestionsUseCaseInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                ),
            )
            ->set(
                LineItemRouteRegistrar::class,
                static fn (ContainerInterface $c): LineItemRouteRegistrar => new LineItemRouteRegistrar(
                    self::resolve($c, ListLineItemSuggestionsHandler::class),
                ),
            );
    }

    /** @return RequestScopedHolder<int> */
    private static function orgHolder(ContainerInterface $c): RequestScopedHolder
    {
        $holder = $c->get(ApplicationServiceProvider::ORG_ID_HOLDER);

        if (!$holder instanceof RequestScopedHolder) {
            throw new LogicException('Organization id holder service is invalid.');
        }

        return $holder;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private static function resolve(ContainerInterface $c, string $id): object
    {
        $service = $c->get($id);

        if (!$service instanceof $id) {
            throw new LogicException(sprintf('Service %s is invalid.', $id));
        }

        return $service;
    }
}
