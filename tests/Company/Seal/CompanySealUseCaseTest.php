<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Company\Seal;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Company\Seal\CompanySealRepositoryInterface;
use NeneInvoice\Company\Seal\CompanySealUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryCompanySealRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class CompanySealUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryCompanySealRepository $repo;
    private RecordingAuditRecorder $audit;
    private CompanySealUseCase $useCase;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(7);
        $this->repo  = new InMemoryCompanySealRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();

        $repo  = $this->repo;
        $audit = $this->audit;

        $this->useCase = new CompanySealUseCase(
            $repo,
            new ImmediateTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $exec): CompanySealRepositoryInterface => $repo,
            static fn (DatabaseQueryExecutorInterface $exec): AuditRecorderInterface => $audit,
            $this->holder,
        );
    }

    public function test_get_returns_null_when_unset(): void
    {
        self::assertNull($this->useCase->get());
    }

    public function test_save_persists_and_audits_the_presence_transition(): void
    {
        $this->useCase->save(42, 'BASE64SEAL');

        self::assertSame('BASE64SEAL', $this->useCase->get());
        self::assertCount(1, $this->audit->records);

        $record = $this->audit->records[0];
        self::assertSame(42, $record['actor_user_id']);
        self::assertSame(7, $record['organization_id']);
        self::assertSame('company_settings.seal_updated', $record['action']);
        // The raw image bytes are never written to the audit trail.
        self::assertSame(['has_seal' => false], $record['before']);
        self::assertSame(['has_seal' => true], $record['after']);
    }

    public function test_delete_removes_and_audits(): void
    {
        $this->useCase->save(1, 'SEAL');
        $this->useCase->delete(1);

        self::assertNull($this->useCase->get());
        self::assertSame('company_settings.seal_deleted', $this->audit->records[1]['action']);
        self::assertSame(['has_seal' => true], $this->audit->records[1]['before']);
        self::assertSame(['has_seal' => false], $this->audit->records[1]['after']);
    }
}
