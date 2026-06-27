<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditServiceProvider;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\PdoLineItemRepository;
use NeneInvoice\LineItem\TaxCalculator;
use Psr\Container\ContainerInterface;

/**
 * Wires the RecurringInvoice domain: repository, use cases, handlers, domain
 * exception handlers, and the route registrar (#503).
 */
final readonly class RecurringInvoiceServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                RecurringInvoiceRepositoryInterface::class,
                static function (ContainerInterface $c): RecurringInvoiceRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoRecurringInvoiceRepository($query, self::orgHolder($c));
                },
            )
            ->set(
                CreateRecurringInvoiceUseCase::class,
                static function (ContainerInterface $c): CreateRecurringInvoiceUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new CreateRecurringInvoiceUseCase(
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): RecurringInvoiceRepositoryInterface => new PdoRecurringInvoiceRepository($exec, $orgHolder),
                        static fn (DatabaseQueryExecutorInterface $exec): LineItemRepositoryInterface => new PdoLineItemRepository($exec, $orgHolder),
                        self::resolve($c, ClientRepositoryInterface::class),
                        self::resolve($c, TaxCalculator::class),
                        AuditServiceProvider::recorderFactory($c),
                        $orgHolder,
                    );
                },
            )
            ->set(
                UpdateRecurringInvoiceUseCase::class,
                static function (ContainerInterface $c): UpdateRecurringInvoiceUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new UpdateRecurringInvoiceUseCase(
                        self::recurring($c),
                        self::resolve($c, LineItemRepositoryInterface::class),
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): RecurringInvoiceRepositoryInterface => new PdoRecurringInvoiceRepository($exec, $orgHolder),
                        static fn (DatabaseQueryExecutorInterface $exec): LineItemRepositoryInterface => new PdoLineItemRepository($exec, $orgHolder),
                        self::resolve($c, ClientRepositoryInterface::class),
                        self::resolve($c, TaxCalculator::class),
                        AuditServiceProvider::recorderFactory($c),
                        $orgHolder,
                    );
                },
            )
            ->set(
                DeleteRecurringInvoiceUseCase::class,
                static function (ContainerInterface $c): DeleteRecurringInvoiceUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new DeleteRecurringInvoiceUseCase(
                        self::recurring($c),
                        self::resolve($c, LineItemRepositoryInterface::class),
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): RecurringInvoiceRepositoryInterface => new PdoRecurringInvoiceRepository($exec, $orgHolder),
                        AuditServiceProvider::recorderFactory($c),
                        $orgHolder,
                    );
                },
            )
            ->set(
                ListRecurringInvoicesUseCase::class,
                static fn (ContainerInterface $c): ListRecurringInvoicesUseCase => new ListRecurringInvoicesUseCase(self::recurring($c)),
            )
            ->set(
                GetRecurringInvoiceByIdUseCase::class,
                static fn (ContainerInterface $c): GetRecurringInvoiceByIdUseCase => new GetRecurringInvoiceByIdUseCase(
                    self::recurring($c),
                    self::resolve($c, LineItemRepositoryInterface::class),
                ),
            )
            ->set(
                ListRecurringInvoicesHandler::class,
                static fn (ContainerInterface $c): ListRecurringInvoicesHandler => new ListRecurringInvoicesHandler(
                    self::resolve($c, ListRecurringInvoicesUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                GetRecurringInvoiceHandler::class,
                static fn (ContainerInterface $c): GetRecurringInvoiceHandler => new GetRecurringInvoiceHandler(
                    self::resolve($c, GetRecurringInvoiceByIdUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                CreateRecurringInvoiceHandler::class,
                static fn (ContainerInterface $c): CreateRecurringInvoiceHandler => new CreateRecurringInvoiceHandler(
                    self::resolve($c, CreateRecurringInvoiceUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                UpdateRecurringInvoiceHandler::class,
                static fn (ContainerInterface $c): UpdateRecurringInvoiceHandler => new UpdateRecurringInvoiceHandler(
                    self::resolve($c, UpdateRecurringInvoiceUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                DeleteRecurringInvoiceHandler::class,
                static fn (ContainerInterface $c): DeleteRecurringInvoiceHandler => new DeleteRecurringInvoiceHandler(
                    self::resolve($c, DeleteRecurringInvoiceUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                RecurringInvoiceNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): RecurringInvoiceNotFoundExceptionHandler => new RecurringInvoiceNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                RecurringInvoiceValidationExceptionHandler::class,
                static fn (ContainerInterface $c): RecurringInvoiceValidationExceptionHandler => new RecurringInvoiceValidationExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                RecurringInvoiceRouteRegistrar::class,
                static function (ContainerInterface $c): RecurringInvoiceRouteRegistrar {
                    $list   = $c->get(ListRecurringInvoicesHandler::class);
                    $get    = $c->get(GetRecurringInvoiceHandler::class);
                    $create = $c->get(CreateRecurringInvoiceHandler::class);
                    $update = $c->get(UpdateRecurringInvoiceHandler::class);
                    $delete = $c->get(DeleteRecurringInvoiceHandler::class);

                    if (!$list instanceof ListRecurringInvoicesHandler || !$get instanceof GetRecurringInvoiceHandler || !$create instanceof CreateRecurringInvoiceHandler || !$update instanceof UpdateRecurringInvoiceHandler || !$delete instanceof DeleteRecurringInvoiceHandler) {
                        throw new LogicException('Recurring invoice handler services are invalid.');
                    }

                    return new RecurringInvoiceRouteRegistrar($list, $get, $create, $update, $delete);
                },
            );
    }

    private static function recurring(ContainerInterface $c): RecurringInvoiceRepositoryInterface
    {
        $repo = $c->get(RecurringInvoiceRepositoryInterface::class);

        if (!$repo instanceof RecurringInvoiceRepositoryInterface) {
            throw new LogicException('Recurring invoice repository service is invalid.');
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

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        return self::resolve($c, JsonResponseFactory::class);
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        return self::resolve($c, ProblemDetailsResponseFactory::class);
    }
}
