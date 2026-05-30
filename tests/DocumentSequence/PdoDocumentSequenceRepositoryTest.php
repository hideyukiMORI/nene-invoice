<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\DocumentSequence;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\DocumentSequence\PdoDocumentSequenceRepository;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests for PdoDocumentSequenceRepository: atomic allocation logic,
 * org/type/year isolation, and INSERT-fallback-on-conflict behaviour. The
 * organization is read from the request-scoped holder.
 */
final class PdoDocumentSequenceRepositoryTest extends TestCase
{
    private PdoDocumentSequenceRepository $repo;
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

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/document_sequences.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new PdoDocumentSequenceRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->holder);
    }

    public function test_first_call_initialises_at_one(): void
    {
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
    }

    public function test_subsequent_calls_increment(): void
    {
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Invoice->value, 2026));
        self::assertSame(2, $this->repo->nextNumber(DocumentType::Invoice->value, 2026));
        self::assertSame(3, $this->repo->nextNumber(DocumentType::Invoice->value, 2026));
    }

    public function test_isolated_by_document_type(): void
    {
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Invoice->value, 2026));
    }

    public function test_isolated_by_organization(): void
    {
        $this->holder->set(1);
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
        $this->holder->set(2);
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
    }

    public function test_isolated_by_year(): void
    {
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2025));
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
    }

    public function test_mixed_interleaving_stays_correct(): void
    {
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
        self::assertSame(1, $this->repo->nextNumber(DocumentType::Invoice->value, 2026));
        self::assertSame(2, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
        self::assertSame(2, $this->repo->nextNumber(DocumentType::Invoice->value, 2026));
        self::assertSame(3, $this->repo->nextNumber(DocumentType::Quote->value, 2026));
    }
}
