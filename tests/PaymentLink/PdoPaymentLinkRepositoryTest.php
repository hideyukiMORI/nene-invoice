<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\PaymentLink;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\PaymentLink\PaymentLink;
use NeneInvoice\PaymentLink\PaymentLinkStatus;
use NeneInvoice\PaymentLink\PdoPaymentLinkRepository;
use PHPUnit\Framework\TestCase;

final class PdoPaymentLinkRepositoryTest extends TestCase
{
    private PdoPaymentLinkRepository $repo;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/payment_links.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoPaymentLinkRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    private function activeLink(int $organizationId, int $invoiceId, string $rawToken): PaymentLink
    {
        return new PaymentLink(
            organizationId: $organizationId,
            invoiceId: $invoiceId,
            tokenHash: hash('sha256', $rawToken),
            gateway: 'payjp',
            status: PaymentLinkStatus::Active,
            expiresAt: '2026-06-13 03:00:00',
            createdAt: '2026-06-06 03:00:00',
            updatedAt: '2026-06-06 03:00:00',
        );
    }

    public function test_saves_and_finds_by_hash(): void
    {
        $id = $this->repo->save($this->activeLink(1, 5, 'raw-abc'));
        self::assertGreaterThan(0, $id);

        $found = $this->repo->findByHash(hash('sha256', 'raw-abc'));
        self::assertNotNull($found);
        self::assertSame(5, $found->invoiceId);
        self::assertSame(PaymentLinkStatus::Active, $found->status);
        self::assertSame('payjp', $found->gateway);
        self::assertNull($found->gatewaySessionId);
    }

    public function test_find_active_by_invoice_is_org_scoped(): void
    {
        $this->repo->save($this->activeLink(1, 5, 'raw-org1'));
        $this->repo->save($this->activeLink(2, 5, 'raw-org2'));

        $found = $this->repo->findActiveByInvoiceId(5);
        self::assertNotNull($found);
        self::assertSame(1, $found->organizationId, 'must only see the caller-org link');
    }

    public function test_mark_revoked_is_org_scoped_and_idempotent(): void
    {
        $id = $this->repo->save($this->activeLink(1, 5, 'raw-revoke'));

        self::assertTrue($this->repo->markRevoked($id, '2026-06-07 00:00:00'));
        self::assertSame(PaymentLinkStatus::Revoked, $this->repo->findById($id)?->status);

        // Second revoke is a no-op (already revoked).
        self::assertFalse($this->repo->markRevoked($id, '2026-06-08 00:00:00'));
    }

    public function test_find_by_id_hides_other_org(): void
    {
        $id = $this->repo->save($this->activeLink(2, 9, 'raw-foreign'));

        self::assertNull($this->repo->findById($id));
        self::assertFalse($this->repo->markRevoked($id, '2026-06-07 00:00:00'));
    }

    public function test_mark_paid_records_charge_id_and_is_findable_by_session(): void
    {
        $id = $this->repo->save($this->activeLink(1, 5, 'raw-paid'));

        self::assertTrue($this->repo->markPaid($id, 'ch_test_abc', '2026-06-07 00:00:00'));

        $byId = $this->repo->findById($id);
        self::assertNotNull($byId);
        self::assertSame(PaymentLinkStatus::Paid, $byId->status);
        self::assertSame('ch_test_abc', $byId->gatewaySessionId);
        self::assertSame('2026-06-07 00:00:00', $byId->paidAt);

        // Reverse lookup by gateway charge id (webhook path) is not org-scoped.
        $bySession = $this->repo->findByGatewaySessionId('ch_test_abc');
        self::assertNotNull($bySession);
        self::assertSame($id, $bySession->id);

        // markPaid is idempotent: a second call (now non-active) is a no-op.
        self::assertFalse($this->repo->markPaid($id, 'ch_test_abc', '2026-06-08 00:00:00'));
    }

    public function test_mark_paid_is_org_scoped(): void
    {
        $id = $this->repo->save($this->activeLink(2, 9, 'raw-foreign-paid'));

        self::assertFalse($this->repo->markPaid($id, 'ch_x', '2026-06-07 00:00:00'));
    }
}
