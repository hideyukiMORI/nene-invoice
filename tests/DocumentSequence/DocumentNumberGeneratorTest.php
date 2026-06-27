<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\DocumentSequence;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\DocumentSequence\DocumentSequenceRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\DocumentSequence\PdoDocumentSequenceRepository;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The `%03d` format is a *minimum* width, not a cap: 1..999 are zero-padded
     * to three digits, and the sequence rolls over to four+ digits past 999
     * (EST-2026-1000) rather than truncating or wrapping. Uses a stub repository
     * to drive exact sequence values without allocating a thousand rows.
     */
    #[DataProvider('formatCases')]
    public function test_pads_to_three_digits_and_does_not_cap_past_999(int $sequence, string $expected): void
    {
        $generator = new DocumentNumberGenerator(new class ($sequence) implements DocumentSequenceRepositoryInterface {
            public function __construct(private int $sequence)
            {
            }

            public function nextNumber(string $docType, int $year): int
            {
                return $this->sequence;
            }
        });

        self::assertSame($expected, $generator->next(DocumentType::Quote, 2026));
    }

    /** @return iterable<string, array{int, string}> */
    public static function formatCases(): iterable
    {
        yield '1 -> 001'       => [1, 'EST-2026-001'];
        yield '9 -> 009'       => [9, 'EST-2026-009'];
        yield '10 -> 010'      => [10, 'EST-2026-010'];
        yield '99 -> 099'      => [99, 'EST-2026-099'];
        yield '100 -> 100'     => [100, 'EST-2026-100'];
        yield '999 -> 999'     => [999, 'EST-2026-999'];
        yield '1000 -> 1000'   => [1000, 'EST-2026-1000'];
        yield '12345 -> 12345' => [12345, 'EST-2026-12345'];
    }
}
