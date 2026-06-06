<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the audit trail: repository, recorder, and the read endpoint (ADR 0008).
 */
final readonly class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                AuditLogRepositoryInterface::class,
                static function (ContainerInterface $c): AuditLogRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoAuditLogRepository($query, self::orgHolder($c));
                },
            )
            ->set(
                AuditRecorderInterface::class,
                static function (ContainerInterface $c): AuditRecorderInterface {
                    $repo = $c->get(AuditLogRepositoryInterface::class);

                    if (!$repo instanceof AuditLogRepositoryInterface) {
                        throw new LogicException('Audit log repository service is invalid.');
                    }

                    return new AuditRecorder($repo, self::clock($c));
                },
            )
            ->set(
                ListAuditLogsUseCaseInterface::class,
                static fn (ContainerInterface $c): ListAuditLogsUseCase => new ListAuditLogsUseCase(self::resolve($c, AuditLogRepositoryInterface::class)),
            )
            ->set(
                ListAuditLogsHandler::class,
                static fn (ContainerInterface $c): ListAuditLogsHandler => new ListAuditLogsHandler(
                    self::resolve($c, ListAuditLogsUseCaseInterface::class),
                    self::resolve($c, JsonResponseFactory::class),
                ),
            )
            ->set(
                ExportAuditLogsCsvUseCaseInterface::class,
                static fn (ContainerInterface $c): ExportAuditLogsCsvUseCase => new ExportAuditLogsCsvUseCase(
                    self::resolve($c, AuditLogRepositoryInterface::class),
                ),
            )
            ->set(
                ExportAuditLogsCsvHandler::class,
                static fn (ContainerInterface $c): ExportAuditLogsCsvHandler => new ExportAuditLogsCsvHandler(
                    self::resolve($c, ExportAuditLogsCsvUseCaseInterface::class),
                    self::resolve($c, Psr17Factory::class),
                ),
            )
            ->set(
                AuditRouteRegistrar::class,
                static function (ContainerInterface $c): AuditRouteRegistrar {
                    $list   = $c->get(ListAuditLogsHandler::class);
                    $export = $c->get(ExportAuditLogsCsvHandler::class);

                    if (!$list instanceof ListAuditLogsHandler || !$export instanceof ExportAuditLogsCsvHandler) {
                        throw new LogicException('Audit log handler services are invalid.');
                    }

                    return new AuditRouteRegistrar($list, $export);
                },
            );
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

    /** @return RequestScopedHolder<int> */
    private static function orgHolder(ContainerInterface $c): RequestScopedHolder
    {
        $holder = $c->get(ApplicationServiceProvider::ORG_ID_HOLDER);

        if (!$holder instanceof RequestScopedHolder) {
            throw new LogicException('Org id holder service is invalid.');
        }

        return $holder;
    }

    private static function clock(ContainerInterface $c): ClockInterface
    {
        $clock = $c->get(ClockInterface::class);

        if (!$clock instanceof ClockInterface) {
            throw new LogicException('Clock service is invalid.');
        }

        return $clock;
    }

    /**
     * Builds a recorder factory bound to a transaction's executor, so the audit
     * write commits or rolls back together with the business writes (Issue #352).
     * Use cases that mutate inside {@see DatabaseTransactionManagerInterface} take
     * this closure and build the recorder from the transaction-bound executor.
     *
     * @return Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface
     */
    public static function recorderFactory(ContainerInterface $c): Closure
    {
        $orgHolder = self::orgHolder($c);
        $clock     = self::clock($c);

        return static fn (DatabaseQueryExecutorInterface $exec): AuditRecorderInterface
            => new AuditRecorder(new PdoAuditLogRepository($exec, $orgHolder), $clock);
    }
}
