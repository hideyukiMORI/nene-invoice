<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\DocumentSequence;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\DocumentSequence\PdoDocumentSequenceRepository;
use PHPUnit\Framework\TestCase;

final class DocumentNumberGeneratorTest extends TestCase
{
    private DocumentNumberGenerator $generator;

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

        $this->generator = new DocumentNumberGenerator(
            new PdoDocumentSequenceRepository(new PdoDatabaseQueryExecutor($factory, $pdo)),
        );
    }

    public function test_numbers_increment_sequentially_with_format(): void
    {
        self::assertSame('EST-2026-001', $this->generator->next(1, DocumentType::Quote, 2026));
        self::assertSame('EST-2026-002', $this->generator->next(1, DocumentType::Quote, 2026));
        self::assertSame('EST-2026-003', $this->generator->next(1, DocumentType::Quote, 2026));
    }

    public function test_sequences_are_isolated_by_org_type_and_year(): void
    {
        self::assertSame('EST-2026-001', $this->generator->next(1, DocumentType::Quote, 2026));
        // Different document type → its own counter.
        self::assertSame('INV-2026-001', $this->generator->next(1, DocumentType::Invoice, 2026));
        // Different organization → its own counter.
        self::assertSame('EST-2026-001', $this->generator->next(2, DocumentType::Quote, 2026));
        // Different year → resets.
        self::assertSame('EST-2027-001', $this->generator->next(1, DocumentType::Quote, 2027));
        // Original counter continues.
        self::assertSame('EST-2026-002', $this->generator->next(1, DocumentType::Quote, 2026));
    }
}
