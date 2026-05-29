<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
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

                    return new PdoAuditLogRepository($query);
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
                ListAuditLogsUseCase::class,
                static fn (ContainerInterface $c): ListAuditLogsUseCase => new ListAuditLogsUseCase(self::resolve($c, AuditLogRepositoryInterface::class)),
            )
            ->set(
                ListAuditLogsHandler::class,
                static fn (ContainerInterface $c): ListAuditLogsHandler => new ListAuditLogsHandler(
                    self::resolve($c, ListAuditLogsUseCase::class),
                    self::resolve($c, JsonResponseFactory::class),
                    self::resolve($c, ProblemDetailsResponseFactory::class),
                ),
            )
            ->set(
                AuditRouteRegistrar::class,
                static function (ContainerInterface $c): AuditRouteRegistrar {
                    $list = $c->get(ListAuditLogsHandler::class);

                    if (!$list instanceof ListAuditLogsHandler) {
                        throw new LogicException('Audit log handler service is invalid.');
                    }

                    return new AuditRouteRegistrar($list);
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
}
