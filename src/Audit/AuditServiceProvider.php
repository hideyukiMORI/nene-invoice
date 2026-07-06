<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use LogicException;
use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditPayloadMode;
use Nene2\Audit\AuditRecorder;
use Nene2\Audit\AuditRecorderFactory;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Audit\AuditTableConfig;
use Nene2\Audit\PdoAuditEventRepository;
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
 * Wires the framework audit module (`Nene2\Audit`, ADR 0014) onto Invoice's
 * existing `audit_logs` table — no re-migration.
 *
 * The whole product/framework seam is {@see AuditTableConfig}: it points the
 * framework repository and recorder at Invoice's physical columns (auto-increment
 * `BIGINT` id, `actor_user_id`, `created_at`, `before_json`/`after_json`, and no
 * metadata column). Records are written by the framework's transaction-atomic
 * {@see AuditRecorderFactoryInterface::forExecutor()} (Issue #352 atomicity
 * preserved); the non-transactional {@see AuditRecorderInterface} serves the one
 * mutation-free caller (invoice-email send). Reads go through the product's
 * {@see AuditLogRepositoryInterface}, which keeps the org-scoping and actor-email
 * concerns the framework contract intentionally omits.
 */
final readonly class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                AuditTableConfig::class,
                static fn (): AuditTableConfig => self::tableConfig(),
            )
            // Non-transactional repository, used by the read side and the
            // non-transactional recorder. Mutating use cases build their own
            // repository bound to the transaction executor via
            // AuditRecorderFactoryInterface::forExecutor().
            ->set(
                AuditEventRepositoryInterface::class,
                static fn (ContainerInterface $c): AuditEventRepositoryInterface
                    => new PdoAuditEventRepository(self::query($c), self::tableConfig()),
            )
            ->set(
                AuditRecorderFactoryInterface::class,
                // No organization holder is passed: every Invoice use case sets
                // AuditEvent::$organizationId explicitly (including holder-less
                // superadmin provisioning), so the recorder never needs the
                // fallback. Invoice's holder is also RequestScopedHolder<int>,
                // which is invariant against the framework's <string|int>.
                static fn (ContainerInterface $c): AuditRecorderFactoryInterface
                    => new AuditRecorderFactory(self::clock($c), self::tableConfig()),
            )
            ->set(
                // Non-transactional recorder for callers that record without a
                // surrounding business mutation (invoice-email send).
                AuditRecorderInterface::class,
                static function (ContainerInterface $c): AuditRecorderInterface {
                    $repo = $c->get(AuditEventRepositoryInterface::class);

                    if (!$repo instanceof AuditEventRepositoryInterface) {
                        throw new LogicException('Audit event repository service is invalid.');
                    }

                    return new AuditRecorder($repo, self::clock($c));
                },
            )
            ->set(
                AuditLogRepositoryInterface::class,
                static function (ContainerInterface $c): AuditLogRepositoryInterface {
                    $events = $c->get(AuditEventRepositoryInterface::class);

                    if (!$events instanceof AuditEventRepositoryInterface) {
                        throw new LogicException('Audit event repository service is invalid.');
                    }

                    return new PdoAuditLogRepository($events, self::query($c), self::orgHolder($c));
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
     * Points the framework audit module at Invoice's existing `audit_logs` table
     * (ADR 0014). This is the single knob the product turns to adopt `Nene2\Audit`
     * without re-migrating: physical column names, auto-increment numeric id
     * (`idIsAutoIncrement: true`), canonical before/after payload mode, and no
     * metadata column (`metadataColumn: null`).
     */
    private static function tableConfig(): AuditTableConfig
    {
        return new AuditTableConfig(
            table: 'audit_logs',
            mode: AuditPayloadMode::BeforeAfter,
            idColumn: 'id',
            actionColumn: 'action',
            entityTypeColumn: 'entity_type',
            entityIdColumn: 'entity_id',
            actorColumn: 'actor_user_id',
            organizationColumn: 'organization_id',
            occurredAtColumn: 'created_at',
            metadataColumn: null,
            beforeColumn: 'before_json',
            afterColumn: 'after_json',
            payloadColumn: null,
            idIsAutoIncrement: true,
        );
    }

    /**
     * Resolves the framework audit recorder factory for a mutating use case.
     * Binding to the transaction executor happens per call via
     * {@see AuditRecorderFactoryInterface::forExecutor()}, so the audit write
     * commits or rolls back together with the business writes (Issue #352).
     */
    public static function recorderFactory(ContainerInterface $c): AuditRecorderFactoryInterface
    {
        return self::resolve($c, AuditRecorderFactoryInterface::class);
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

    private static function query(ContainerInterface $c): DatabaseQueryExecutorInterface
    {
        return self::resolve($c, DatabaseQueryExecutorInterface::class);
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
        return self::resolve($c, ClockInterface::class);
    }
}
