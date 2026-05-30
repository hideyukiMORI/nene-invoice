<?php

declare(strict_types=1);

namespace NeneInvoice\DocumentSequence;

use Nene2\Database\DatabaseConstraintException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;
use RuntimeException;

final readonly class PdoDocumentSequenceRepository implements DocumentSequenceRepositoryInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function nextNumber(string $docType, int $year): int
    {
        $organizationId = $this->orgId->get();

        // Increment the existing counter; if there is no row yet for this
        // org/type/year, create it. A concurrent INSERT (unique conflict) means
        // another caller created the row first, so we increment instead.
        $affected = $this->increment($organizationId, $docType, $year);

        if ($affected === 0) {
            try {
                $this->query->execute(
                    'INSERT INTO document_sequences (organization_id, doc_type, year, last_number) VALUES (?, ?, ?, 1)',
                    [$organizationId, $docType, $year],
                );
            } catch (DatabaseConstraintException) {
                $this->increment($organizationId, $docType, $year);
            }
        }

        $row = $this->query->fetchOne(
            'SELECT last_number FROM document_sequences WHERE organization_id = ? AND doc_type = ? AND year = ?',
            [$organizationId, $docType, $year],
        );

        if ($row === null) {
            throw new RuntimeException('Document sequence row missing immediately after allocation.');
        }

        return (int) $row['last_number'];
    }

    private function increment(int $organizationId, string $docType, int $year): int
    {
        return $this->query->execute(
            'UPDATE document_sequences SET last_number = last_number + 1 WHERE organization_id = ? AND doc_type = ? AND year = ?',
            [$organizationId, $docType, $year],
        );
    }
}
