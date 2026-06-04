<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * Persistence for invoices. Every query is scoped to the organization held in
 * the request-scoped org holder (ADR 0006); callers never pass an organization
 * id. Reads exclude soft-deleted rows; `delete` is soft.
 */
interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;

    /**
     * Whether an invoice already exists for the given quote in the resolved
     * organization. Used to enforce one-invoice-per-quote on conversion.
     */
    public function existsForQuote(int $quoteId): bool;

    /** @return list<Invoice> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /** @return list<Invoice> */
    public function findFiltered(InvoiceListFilter $filter, int $limit, int $offset): array;

    public function countFiltered(InvoiceListFilter $filter): int;

    /**
     * Admin list: filtered + searched + sorted, joined with the client name.
     *
     * @return list<InvoiceListRow>
     */
    public function findForAdminList(InvoiceListFilter $filter, InvoiceSort $sort, int $limit, int $offset): array;

    public function countForAdminList(InvoiceListFilter $filter): int;

    /**
     * Returns counts and recent unpaid invoices for the dashboard in a single query.
     * `$now` is a comparable datetime string (e.g. `Y-m-d H:i:s`).
     *
     * @return array{unpaid_count: int, overdue_count: int, recent_unpaid: list<Invoice>}
     */
    public function getDashboardData(string $now): array;

    /**
     * Total (cents) and count of invoices *issued* within [start, end) — the
     * billing-issuance metric (drafts are excluded; bucketed by `issued_at`).
     *
     * @return array{cents: int, count: int}
     */
    public function billedTotalBetween(string $startInclusive, string $endExclusive): array;

    /**
     * Issued invoices within [start, end) as `{issued_at, total_cents}` rows —
     * used to build daily-cumulative billing (drafts excluded). Date grouping is
     * done in PHP so the query stays dialect-agnostic.
     *
     * @return list<array{issued_at: string, total_cents: int}>
     */
    public function billedRowsBetween(string $startInclusive, string $endExclusive): array;

    public function save(Invoice $invoice): int;

    /** @throws InvoiceNotFoundException */
    public function update(Invoice $invoice): void;

    /** @throws InvoiceNotFoundException */
    public function delete(int $id): void;

    /**
     * Returns all non-draft, non-deleted invoices for the organization joined
     * with the client name. Intended for CSV export only.
     *
     * @return list<array{invoice_number: string, issued_at: string|null, due_at: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string, is_qualified_invoice: bool}>
     */
    public function findIssuedForExport(): array;
}
