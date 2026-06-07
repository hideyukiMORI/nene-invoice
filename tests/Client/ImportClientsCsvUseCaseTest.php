<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientImportTemplate;
use NeneInvoice\Client\ClientListFilter;
use NeneInvoice\Client\ImportClientsCsvUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ImportClientsCsvUseCaseTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryClientRepository $repo;
    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo  = new InMemoryClientRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
    }

    private function useCase(): ImportClientsCsvUseCase
    {
        return new ImportClientsCsvUseCase(
            $this->repo,
            new ImmediateTransactionManager(),
            fn () => $this->repo,
            fn () => $this->audit,
            $this->holder,
        );
    }

    /**
     * @param list<list<string>> $rows
     */
    private function csv(array $rows): string
    {
        $lines = [implode(',', ClientImportTemplate::HEADER)];
        foreach ($rows as $r) {
            $lines[] = implode(',', $r);
        }

        return implode("\n", $lines) . "\n";
    }

    private function clientCount(): int
    {
        return $this->repo->countForAdminList(new ClientListFilter());
    }

    public function test_rejects_wrong_header(): void
    {
        $result = $this->useCase()->execute(1, "name,email\nAcme,a@b.c\n", false);

        self::assertFalse($result->accepted);
        self::assertNotNull($result->formatError);
        self::assertSame(0, $this->clientCount());
    }

    public function test_creates_new_clients_and_audits(): void
    {
        $csv = $this->csv([
            ['clients/v1', '', '株式会社アルファ', 'アルファ', '田中', 'a@example.com', '東京', 'T1234567890123'],
            ['', '', '合同会社ベータ', '', '', '', '', ''],
        ]);

        $result = $this->useCase()->execute(42, $csv, false);

        self::assertTrue($result->accepted);
        self::assertSame(2, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(2, $this->clientCount());
        self::assertCount(2, $this->audit->records);
        self::assertSame('client.created', $this->audit->records[0]['action']);
    }

    public function test_updates_existing_by_id(): void
    {
        $id = $this->repo->save(new Client(organizationId: 1, name: 'Before'));

        $result = $this->useCase()->execute(9, $this->csv([
            ['clients/v1', (string) $id, 'After', '', '', '', '', ''],
        ]), false);

        self::assertTrue($result->accepted);
        self::assertSame(0, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame('After', $this->repo->findById($id)?->name);
    }

    public function test_invalid_registration_rejects_whole_file(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['clients/v1', '', 'Good', '', '', '', '', 'T1234567890123'],
            ['clients/v1', '', 'Bad', '', '', '', '', '12345'],
        ]), false);

        self::assertFalse($result->accepted);
        self::assertSame(0, $this->clientCount(), 'all-or-nothing: nothing is written when any row fails');
        self::assertSame('invalid_registration_number', $result->errors[0]['code']);
        self::assertSame(3, $result->errors[0]['row']); // header=1, Good=2, Bad=3
    }

    public function test_unknown_id_is_rejected(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['clients/v1', '999', 'Ghost', '', '', '', '', ''],
        ]), false);

        self::assertFalse($result->accepted);
        self::assertSame('client_not_found', $result->errors[0]['code']);
        self::assertSame(0, $this->clientCount());
    }

    public function test_required_name_is_rejected(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['clients/v1', '', '', '', '', '', '', ''],
        ]), false);

        self::assertFalse($result->accepted);
        self::assertSame('required', $result->errors[0]['code']);
    }

    public function test_dry_run_validates_without_writing(): void
    {
        $result = $this->useCase()->execute(1, $this->csv([
            ['clients/v1', '', '株式会社アルファ', '', '', '', '', ''],
        ]), true);

        self::assertTrue($result->accepted);
        self::assertTrue($result->dryRun);
        self::assertSame(1, $result->created);
        self::assertSame(0, $this->clientCount(), 'dry-run writes nothing');
        self::assertCount(0, $this->audit->records);
    }
}
