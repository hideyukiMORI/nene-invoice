<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\PaymentLink;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\PaymentLink\PaymentLink;
use NeneInvoice\PaymentLink\PaymentLinkStatus;
use NeneInvoice\PaymentLink\RevokeOutcome;
use NeneInvoice\PaymentLink\RevokePaymentLinkUseCase;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryPaymentLinkRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class RevokePaymentLinkUseCaseTest extends TestCase
{
    private InMemoryPaymentLinkRepository $links;

    private RevokePaymentLinkUseCase $useCase;

    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $holder = new RequestScopedHolder();
        $holder->set(1);
        $this->links   = new InMemoryPaymentLinkRepository(1);
        $this->audit   = new RecordingAuditRecorder();
        $this->useCase = new RevokePaymentLinkUseCase(new ImmediateTransactionManager(), fn () => $this->links, fn () => $this->audit, new FixedClock(), $holder);
    }

    private function saveLink(int $organizationId, PaymentLinkStatus $status): int
    {
        return $this->links->save(new PaymentLink(
            organizationId: $organizationId,
            invoiceId: 5,
            tokenHash: hash('sha256', 'raw-' . $status->value . '-' . $organizationId),
            gateway: 'payjp',
            status: $status,
            expiresAt: '2026-06-13 03:00:00',
            createdAt: '2026-06-06 03:00:00',
            updatedAt: '2026-06-06 03:00:00',
        ));
    }

    public function test_revokes_active_link_and_audits(): void
    {
        $id = $this->saveLink(1, PaymentLinkStatus::Active);

        $outcome = $this->useCase->execute(7, $id);

        self::assertSame(RevokeOutcome::Revoked, $outcome);
        self::assertSame(PaymentLinkStatus::Revoked, $this->links->findById($id)?->status);

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame('payment_link.revoked', $record['action']);
        self::assertSame('payment_link', $record['entity_type']);
        self::assertSame($id, $record['entity_id']);
        self::assertSame(['status' => 'active'], $record['before']);
        self::assertSame(['status' => 'revoked'], $record['after']);
    }

    public function test_revoking_already_revoked_is_idempotent_no_op(): void
    {
        $id = $this->saveLink(1, PaymentLinkStatus::Revoked);

        $outcome = $this->useCase->execute(7, $id);

        self::assertSame(RevokeOutcome::AlreadyInactive, $outcome);
        self::assertCount(0, $this->audit->records);
    }

    public function test_unknown_id_returns_not_found(): void
    {
        $outcome = $this->useCase->execute(7, 999);

        self::assertSame(RevokeOutcome::NotFound, $outcome);
        self::assertCount(0, $this->audit->records);
    }

    public function test_other_org_link_is_invisible(): void
    {
        // Link belongs to org 2; the use case resolves org 1.
        $id = $this->saveLink(2, PaymentLinkStatus::Active);

        $outcome = $this->useCase->execute(7, $id);

        self::assertSame(RevokeOutcome::NotFound, $outcome);
    }
}
