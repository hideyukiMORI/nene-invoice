<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceToken;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\ServiceToken\PdoServiceTokenAuthorizer;
use PHPUnit\Framework\TestCase;

final class PdoServiceTokenAuthorizerTest extends TestCase
{
    private \PDO $pdo;
    private PdoServiceTokenAuthorizer $authorizer;

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
        $this->authorizer = new PdoServiceTokenAuthorizer(new PdoDatabaseQueryExecutor($factory, $pdo));
    }

    private function insert(string $jti, ?string $revokedAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO service_tokens (organization_id, jti, subject, label, scopes, created_by, created_at, expires_at, revoked_at)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([$jti, 'service:clear', 'NeNe Clear', 'read:invoices', null, '2026-06-09 00:00:00', '2026-07-09 00:00:00', $revokedAt]);
    }

    public function test_active_token_is_active(): void
    {
        $this->insert('live', null);
        self::assertTrue($this->authorizer->isActive('live'));
    }

    public function test_revoked_token_is_not_active(): void
    {
        $this->insert('dead', '2026-06-09 10:00:00');
        self::assertFalse($this->authorizer->isActive('dead'));
    }

    public function test_unknown_jti_is_not_active(): void
    {
        self::assertFalse($this->authorizer->isActive('missing'));
    }
}
