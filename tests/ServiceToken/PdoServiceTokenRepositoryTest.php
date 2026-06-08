<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceToken;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ServiceToken\PdoServiceTokenRepository;
use NeneInvoice\ServiceToken\ServiceToken;
use NeneInvoice\ServiceToken\ServiceTokenNotFoundException;
use PHPUnit\Framework\TestCase;

final class PdoServiceTokenRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoServiceTokenRepository $repository;
    private \PDO $pdo;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $pdo = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/service_tokens.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->pdo = $pdo;
        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoServiceTokenRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId);
    }

    /**
     * @param list<string> $scopes
     */
    private function newToken(string $jti, array $scopes = ['read:invoices']): ServiceToken
    {
        return new ServiceToken(
            id: null,
            organizationId: 1,
            jti: $jti,
            subject: 'service:clear',
            label: 'NeNe Clear',
            scopes: $scopes,
            createdBy: 7,
            createdAt: '2026-06-09 00:00:00',
            expiresAt: '2026-07-09 00:00:00',
            revokedAt: null,
        );
    }

    public function test_save_and_find_round_trips_all_fields(): void
    {
        $id = $this->repository->save($this->newToken('jti-a', ['read:invoices', 'write:payments']));

        $found = $this->repository->findById($id);

        self::assertNotNull($found);
        self::assertSame($id, $found->id);
        self::assertSame('jti-a', $found->jti);
        self::assertSame('service:clear', $found->subject);
        self::assertSame('NeNe Clear', $found->label);
        self::assertSame(['read:invoices', 'write:payments'], $found->scopes);
        self::assertSame(7, $found->createdBy);
        self::assertNull($found->revokedAt);
        self::assertFalse($found->isRevoked());
    }

    public function test_list_is_org_scoped_and_newest_first(): void
    {
        $this->repository->save($this->newToken('jti-1'));
        $this->repository->save($this->newToken('jti-2'));
        $this->insertForeignOrgToken('jti-foreign');

        $items = $this->repository->findAll(50, 0);

        self::assertCount(2, $items);
        self::assertSame('jti-2', $items[0]->jti);
        self::assertSame('jti-1', $items[1]->jti);
        self::assertSame(2, $this->repository->count());
    }

    public function test_revoke_sets_timestamp_and_is_idempotent(): void
    {
        $id = $this->repository->save($this->newToken('jti-r'));

        $this->repository->revoke($id, '2026-06-09 12:00:00');
        $first = $this->repository->findById($id);
        self::assertNotNull($first);
        self::assertSame('2026-06-09 12:00:00', $first->revokedAt);
        self::assertTrue($first->isRevoked());

        // Re-revoking keeps the original timestamp and does not throw.
        $this->repository->revoke($id, '2026-06-10 09:00:00');
        $again = $this->repository->findById($id);
        self::assertNotNull($again);
        self::assertSame('2026-06-09 12:00:00', $again->revokedAt);
    }

    public function test_revoke_unknown_id_throws(): void
    {
        $this->expectException(ServiceTokenNotFoundException::class);
        $this->repository->revoke(999, '2026-06-09 12:00:00');
    }

    public function test_revoke_foreign_org_token_throws(): void
    {
        $foreignId = $this->insertForeignOrgToken('jti-foreign');

        $this->expectException(ServiceTokenNotFoundException::class);
        $this->repository->revoke($foreignId, '2026-06-09 12:00:00');
    }

    private function insertForeignOrgToken(string $jti): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO service_tokens (organization_id, jti, subject, label, scopes, created_by, created_at, expires_at, revoked_at)
             VALUES (2, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([$jti, 'service:clear', 'Other org', 'read:invoices', null, '2026-06-09 00:00:00', '2026-07-09 00:00:00', null]);

        return (int) $this->pdo->lastInsertId();
    }
}
