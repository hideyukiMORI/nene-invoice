<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientListFilter;
use NeneInvoice\Client\ExportClientsCsvUseCase;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class ExportClientsCsvUseCaseTest extends TestCase
{
    public function test_returns_csv_with_bom_and_header_when_empty(): void
    {
        $csv = (new ExportClientsCsvUseCase(new InMemoryClientRepository()))->execute(new ClientListFilter());

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString('取引先名', $csv);
        self::assertStringContainsString('登録番号', $csv);
    }

    public function test_csv_contains_client_row(): void
    {
        $repo = new InMemoryClientRepository();
        $repo->save(new Client(
            organizationId: 1,
            name: '株式会社サンプル',
            contactName: '田中 一郎',
            email: 'tanaka@example.com',
            billingAddress: '東京都〇〇',
            registrationNumber: 'T1234567890123',
        ));

        $csv = (new ExportClientsCsvUseCase($repo))->execute(new ClientListFilter());

        self::assertStringContainsString('株式会社サンプル', $csv);
        self::assertStringContainsString('田中 一郎', $csv);
        self::assertStringContainsString('tanaka@example.com', $csv);
        self::assertStringContainsString('T1234567890123', $csv);
    }

    public function test_reflects_search_filter(): void
    {
        $repo = new InMemoryClientRepository();
        $repo->save(new Client(organizationId: 1, name: '株式会社アルファ'));
        $repo->save(new Client(organizationId: 1, name: '合同会社ベータ'));

        $csv = (new ExportClientsCsvUseCase($repo))->execute(new ClientListFilter('アルファ'));

        self::assertStringContainsString('アルファ', $csv);
        self::assertStringNotContainsString('ベータ', $csv);
    }

    public function test_neutralizes_formula_injection_in_client_name(): void
    {
        $repo = new InMemoryClientRepository();
        $repo->save(new Client(organizationId: 1, name: '=HYPERLINK("http://evil")'));

        $csv = (new ExportClientsCsvUseCase($repo))->execute(new ClientListFilter());

        // The malicious name is rendered as text (single-quote prefixed), not a formula.
        self::assertStringContainsString('\'=HYPERLINK', $csv);
    }
}
