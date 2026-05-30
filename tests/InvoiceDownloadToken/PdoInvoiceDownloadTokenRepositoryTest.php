<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadToken;
use NeneInvoice\InvoiceDownloadToken\PdoInvoiceDownloadTokenRepository;
use PHPUnit\Framework\TestCase;

final class PdoInvoiceDownloadTokenRepositoryTest extends TestCase
{
    private PdoInvoiceDownloadTokenRepository $repo;

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
        $pdo     = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/invoice_download_tokens.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->repo = new PdoInvoiceDownloadTokenRepository(new PdoDatabaseQueryExecutor($factory, $pdo));
    }

    public function test_saves_and_finds_by_hash(): void
    {
        $token = new InvoiceDownloadToken(
            invoiceId: 5,
            organizationId: 1,
            tokenHash: hash('sha256', 'raw-token-abc'),
            expiresAt: '2026-06-07 00:00:00',
            createdAt: '2026-05-31 00:00:00',
        );

        $id = $this->repo->save($token);
        self::assertGreaterThan(0, $id);

        $found = $this->repo->findByHash(hash('sha256', 'raw-token-abc'));
        self::assertNotNull($found);
        self::assertSame(5, $found->invoiceId);
        self::assertSame(1, $found->organizationId);
        self::assertSame('2026-06-07 00:00:00', $found->expiresAt);
        self::assertSame($id, $found->id);
    }

    public function test_find_returns_null_for_unknown_hash(): void
    {
        self::assertNull($this->repo->findByHash(hash('sha256', 'no-such-token')));
    }

    public function test_is_expired_returns_true_for_past_expiry(): void
    {
        $token = new InvoiceDownloadToken(
            invoiceId: 1,
            organizationId: 1,
            tokenHash: hash('sha256', 'expired-token'),
            expiresAt: '2020-01-01 00:00:00',
            createdAt: '2019-12-25 00:00:00',
        );
        $this->repo->save($token);

        $found = $this->repo->findByHash(hash('sha256', 'expired-token'));
        self::assertNotNull($found);
        self::assertTrue($found->isExpired('2026-05-31 00:00:00'));
    }

    public function test_is_expired_returns_false_for_future_expiry(): void
    {
        $token = new InvoiceDownloadToken(
            invoiceId: 1,
            organizationId: 1,
            tokenHash: hash('sha256', 'valid-token'),
            expiresAt: '2099-12-31 23:59:59',
            createdAt: '2026-05-31 00:00:00',
        );
        $this->repo->save($token);

        $found = $this->repo->findByHash(hash('sha256', 'valid-token'));
        self::assertNotNull($found);
        self::assertFalse($found->isExpired('2026-05-31 00:00:00'));
    }
}
