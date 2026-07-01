<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

/**
 * Reads open receivables for reconciliation matching (#505). Computes each
 * invoice's outstanding balance as `total_cents` minus non-void payments — the
 * same net-outstanding definition used by the dashboard aging query
 * ({@see \NeneInvoice\Payment\PdoPaymentRepository::agingBuckets()}).
 *
 * Booleans are compared with `TRUE`/`FALSE` and statuses with quoted string
 * literals so the SQL is portable across sqlite / mysql / pgsql.
 */
final readonly class PdoMatchCandidateRepository implements MatchCandidateRepositoryInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @return list<OpenReceivable> */
    public function findOpenReceivables(): array
    {
        $rows = $this->query->fetchAll(
            "SELECT t.invoice_id, t.client_id, t.invoice_number, t.outstanding_cents,
                    c.name AS client_name, c.name_kana AS client_name_kana
             FROM (
                SELECT i.id AS invoice_id, i.client_id, i.invoice_number, i.total_cents,
                       i.total_cents - COALESCE(SUM(CASE WHEN p.is_deleted = FALSE THEN p.amount_cents ELSE 0 END), 0) AS outstanding_cents
                FROM invoices i
                LEFT JOIN payments p ON p.invoice_id = i.id
                WHERE i.organization_id = ? AND i.is_deleted = FALSE AND i.status IN ('issued', 'partially_paid')
                GROUP BY i.id, i.client_id, i.invoice_number, i.total_cents
             ) t
             LEFT JOIN clients c ON c.id = t.client_id
             WHERE t.outstanding_cents > 0
             ORDER BY t.invoice_id ASC",
            [$this->orgId->get()],
        );

        return array_map(fn (array $row): OpenReceivable => $this->mapRow($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): OpenReceivable
    {
        return new OpenReceivable(
            invoiceId: (int) $row['invoice_id'],
            clientId: (int) $row['client_id'],
            outstandingCents: (int) $row['outstanding_cents'],
            invoiceNumber: isset($row['invoice_number']) && $row['invoice_number'] !== '' ? (string) $row['invoice_number'] : null,
            clientName: isset($row['client_name']) && $row['client_name'] !== '' ? (string) $row['client_name'] : null,
            clientNameKana: isset($row['client_name_kana']) && $row['client_name_kana'] !== '' ? (string) $row['client_name_kana'] : null,
        );
    }
}
