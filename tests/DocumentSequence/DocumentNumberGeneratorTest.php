<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\DocumentSequence;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\DocumentSequence\PdoDocumentSequenceRepository;
use PHPUnit\Framework\TestCase;

final class DocumentNumberGeneratorTest extends TestCase
{
    private DocumentNumberGenerator $generator;
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
        $pdo = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/document_sequences.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->generator = new DocumentNumberGenerator(
            new PdoDocumentSequenceRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder),
        );
    }

    public function test_numbers_increment_sequentially_with_format(): void
    {
        self::assertSame('EST-2026-001', $this->generator->next(DocumentType::Quote, 2026));
        self::assertSame('EST-2026-002', $this->generator->next(DocumentType::Quote, 2026));
        self::assertSame('EST-2026-003', $this->generator->next(DocumentType::Quote, 2026));
    }

    public function test_sequences_are_isolated_by_org_type_and_year(): void
    {
        self::assertSame('EST-2026-001', $this->generator->next(DocumentType::Quote, 2026));
        // Different document type → its own counter.
        self::assertSame('INV-2026-001', $this->generator->next(DocumentType::Invoice, 2026));
        // Different organization → its own counter.
        $this->holder->set(2);
        self::assertSame('EST-2026-001', $this->generator->next(DocumentType::Quote, 2026));
        $this->holder->set(1);
        // Different year → resets.
        self::assertSame('EST-2027-001', $this->generator->next(DocumentType::Quote, 2027));
        // Original counter continues.
        self::assertSame('EST-2026-002', $this->generator->next(DocumentType::Quote, 2026));
    }
}
