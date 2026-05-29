<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the audit trail: repository and recorder (ADR 0008).
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
            );
    }
}
