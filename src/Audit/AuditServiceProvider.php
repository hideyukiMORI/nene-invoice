<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
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

                    return new AuditRecorder($repo);
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
}
