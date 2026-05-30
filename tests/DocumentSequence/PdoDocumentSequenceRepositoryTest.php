<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\DocumentSequence;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\DocumentSequence\PdoDocumentSequenceRepository;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests for PdoDocumentSequenceRepository: atomic allocation logic,
 * org/type/year isolation, and INSERT-fallback-on-conflict behaviour.
 */
final class PdoDocumentSequenceRepositoryTest extends TestCase
{
    private PdoDocumentSequenceRepository $repo;

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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/document_sequences.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->repo = new PdoDocumentSequenceRepository(new PdoDatabaseQueryExecutor($factory, $pdo));
    }

    public function test_first_call_initialises_at_one(): void
    {
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
    }

    public function test_subsequent_calls_increment(): void
    {
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Invoice->value, 2026));
        self::assertSame(2, $this->repo->nextNumber(1, DocumentType::Invoice->value, 2026));
        self::assertSame(3, $this->repo->nextNumber(1, DocumentType::Invoice->value, 2026));
    }

    public function test_isolated_by_document_type(): void
    {
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Invoice->value, 2026));
    }

    public function test_isolated_by_organization(): void
    {
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
        self::assertSame(1, $this->repo->nextNumber(2, DocumentType::Quote->value, 2026));
    }

    public function test_isolated_by_year(): void
    {
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Quote->value, 2025));
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
    }

    public function test_mixed_interleaving_stays_correct(): void
    {
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
        self::assertSame(1, $this->repo->nextNumber(1, DocumentType::Invoice->value, 2026));
        self::assertSame(2, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
        self::assertSame(2, $this->repo->nextNumber(1, DocumentType::Invoice->value, 2026));
        self::assertSame(3, $this->repo->nextNumber(1, DocumentType::Quote->value, 2026));
    }
}
