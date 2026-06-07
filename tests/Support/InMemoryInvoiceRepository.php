<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceListFilter;
use NeneInvoice\Invoice\InvoiceListRow;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceSort;
use NeneInvoice\Invoice\InvoiceStatus;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Invoice\PdoInvoiceRepository}.
 * `save` keeps the entity's org so tests can seed cross-tenant fixtures. The
 * holder defaults to organization 1 for single-org tests.
 */
final class InMemoryInvoiceRepository implements InvoiceRepositoryInterface
{
    /** @var array<int, Invoice> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function existsForQuote(int $quoteId): bool
    {
        $orgId = $this->orgId->get();
        foreach ($this->byId as $invoice) {
            if ($invoice->quoteId === $quoteId && $invoice->organizationId === $orgId && !$invoice->isDeleted) {
                return true;
            }
        }

        return false;
    }

    public function findById(int $id): ?Invoice
    {
        $invoice = $this->byId[$id] ?? null;

        return $invoice !== null && !$invoice->isDeleted && $invoice->organizationId === $this->orgId->get()
            ? $invoice
            : null;
    }

    /** @return list<Invoice> */
    public function findAll(int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            fn (Invoice $i): bool => $i->organizationId === $this->orgId->get() && !$i->isDeleted,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function count(): int
    {
        return count(array_filter(
            $this->byId,
            fn (Invoice $i): bool => $i->organizationId === $this->orgId->get() && !$i->isDeleted,
        ));
    }

    /** @return list<Invoice> */
    public function findFiltered(InvoiceListFilter $filter, int $limit, int $offset): array
    {
        return array_slice($this->filtered($filter), $offset, $limit);
    }

    public function countFiltered(InvoiceListFilter $filter): int
    {
        return count($this->filtered($filter));
    }

    /**
     * Admin list fake: applies the admin filters + sort. The fake has no client
     * data, so search matches invoice_number only and clientName is empty (the
     * client join is covered by the Pdo integration test).
     *
     * @return list<InvoiceListRow>
     */
    public function findForAdminList(InvoiceListFilter $filter, InvoiceSort $sort, int $limit, int $offset): array
    {
        $matches = $this->adminFiltered($filter);

        usort($matches, static function (Invoice $a, Invoice $b) use ($sort): int {
            $cmp = match ($sort->field) {
                'number'    => strcmp((string) $a->invoiceNumber, (string) $b->invoiceNumber),
                'status'    => strcmp($a->status->value, $b->status->value),
                'issued_at' => strcmp((string) $a->issuedAt, (string) $b->issuedAt),
                'due_at'    => strcmp((string) $a->dueAt, (string) $b->dueAt),
                'total'     => $a->totalCents <=> $b->totalCents,
                default     => ($a->id ?? 0) <=> ($b->id ?? 0),
            };

            return $sort->descending ? -$cmp : $cmp;
        });

        $page = array_slice($matches, $offset, $limit);

        return array_map(static fn (Invoice $i): InvoiceListRow => new InvoiceListRow($i, ''), $page);
    }

    public function countForAdminList(InvoiceListFilter $filter): int
    {
        return count($this->adminFiltered($filter));
    }

    /** @return list<Invoice> */
    private function adminFiltered(InvoiceListFilter $filter): array
    {
        $orgId = $this->orgId->get();
        $today = $filter->todayOrNow();

        return array_values(array_filter($this->byId, static function (Invoice $i) use ($orgId, $filter, $today): bool {
            if ($i->organizationId !== $orgId || $i->isDeleted) {
                return false;
            }
            if ($filter->statuses !== [] && !in_array($i->status->value, $filter->statuses, true)) {
                return false;
            }
            if ($filter->search !== null && stripos((string) $i->invoiceNumber, $filter->search) === false) {
                return false;
            }
            if ($filter->totalMin !== null && $i->totalCents < $filter->totalMin) {
                return false;
            }
            if ($filter->totalMax !== null && $i->totalCents > $filter->totalMax) {
                return false;
            }
            if ($filter->dueFrom !== null && ($i->dueAt === null || $i->dueAt < $filter->dueFrom)) {
                return false;
            }
            if ($filter->dueTo !== null && ($i->dueAt === null || $i->dueAt > $filter->dueTo)) {
                return false;
            }
            if ($filter->overdueOnly) {
                $open = in_array($i->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true);
                if (!$open || $i->dueAt === null || $i->dueAt >= $today) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @return list<Invoice> */
    private function filtered(InvoiceListFilter $filter): array
    {
        $open  = [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid];
        $orgId = $this->orgId->get();

        return array_values(array_filter($this->byId, function (Invoice $i) use ($orgId, $filter, $open): bool {
            if ($i->organizationId !== $orgId || $i->isDeleted) {
                return false;
            }
            if ($filter->statuses !== [] && !in_array($i->status->value, $filter->statuses, true)) {
                return false;
            }
            if ($filter->clientId !== null && $i->clientId !== $filter->clientId) {
                return false;
            }
            if ($filter->dueBefore !== null && ($i->dueAt === null || $i->dueAt >= $filter->dueBefore)) {
                return false;
            }
            if ($filter->dueAfter !== null && ($i->dueAt === null || $i->dueAt <= $filter->dueAfter)) {
                return false;
            }
            if (($filter->outstandingOnly || $filter->overdueOnly) && !in_array($i->status, $open, true)) {
                return false;
            }
            if ($filter->overdueOnly && ($i->dueAt === null || $i->dueAt >= $filter->todayOrNow())) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array{unpaid_count: int, overdue_count: int, recent_unpaid: list<Invoice>}
     */
    public function getDashboardData(string $now): array
    {
        $orgId  = $this->orgId->get();
        $unpaid = array_values(array_filter(
            $this->byId,
            static fn (Invoice $i): bool => $i->organizationId === $orgId
                && !$i->isDeleted
                && in_array($i->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true),
        ));

        $overdueCount = count(array_filter(
            $unpaid,
            static fn (Invoice $i): bool => $i->dueAt !== null && $i->dueAt < $now,
        ));

        $recent = array_slice(array_reverse($unpaid), 0, 5);

        return [
            'unpaid_count'  => count($unpaid),
            'overdue_count' => $overdueCount,
            'recent_unpaid' => $recent,
        ];
    }

    public function billedTotalBetween(string $startInclusive, string $endExclusive): array
    {
        $orgId = $this->orgId->get();
        $cents = 0;
        $count = 0;

        foreach ($this->byId as $i) {
            if ($i->organizationId !== $orgId || $i->isDeleted || $i->issuedAt === null) {
                continue;
            }
            if ($i->issuedAt >= $startInclusive && $i->issuedAt < $endExclusive) {
                $cents += $i->totalCents;
                ++$count;
            }
        }

        return ['cents' => $cents, 'count' => $count];
    }

    public function billedRowsBetween(string $startInclusive, string $endExclusive): array
    {
        $orgId = $this->orgId->get();
        $rows = [];

        foreach ($this->byId as $i) {
            if ($i->organizationId !== $orgId || $i->isDeleted || $i->issuedAt === null) {
                continue;
            }
            if ($i->issuedAt >= $startInclusive && $i->issuedAt < $endExclusive) {
                $rows[] = ['issued_at' => $i->issuedAt, 'total_cents' => $i->totalCents];
            }
        }

        return $rows;
    }

    public function save(Invoice $invoice): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = $this->withId($invoice, $id, $invoice->isDeleted);

        return $id;
    }

    public function update(Invoice $invoice): void
    {
        if ($invoice->id === null || $this->findById($invoice->id) === null) {
            throw new InvoiceNotFoundException($invoice->id ?? 0);
        }

        $this->byId[$invoice->id] = $invoice;
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new InvoiceNotFoundException($id);
        }

        $this->byId[$id] = $this->withId($existing, $id, true);
    }

    /** @return list<array{invoice_number: string, issued_at: string|null, due_at: string|null, client_name: string, subtotal_cents: int, tax_cents: int, total_cents: int, status: string, is_qualified_invoice: bool}> */
    public function findIssuedForExport(InvoiceListFilter $filter): array
    {
        return array_values(array_map(
            static fn (Invoice $i): array => [
                'invoice_number'       => (string) $i->invoiceNumber,
                'issued_at'            => $i->issuedAt,
                'due_at'               => $i->dueAt,
                'client_name'          => '',
                'subtotal_cents'       => $i->subtotalCents,
                'tax_cents'            => $i->taxCents,
                'total_cents'          => $i->totalCents,
                'status'               => $i->status->value,
                'is_qualified_invoice' => $i->isQualifiedInvoice,
            ],
            array_filter(
                $this->adminFiltered($filter),
                static fn (Invoice $i): bool => $i->status !== InvoiceStatus::Draft,
            ),
        ));
    }

    private function withId(Invoice $invoice, int $id, bool $isDeleted): Invoice
    {
        return new Invoice(
            organizationId: $invoice->organizationId,
            clientId: $invoice->clientId,
            status: $invoice->status,
            subtotalCents: $invoice->subtotalCents,
            taxCents: $invoice->taxCents,
            totalCents: $invoice->totalCents,
            isQualifiedInvoice: $invoice->isQualifiedInvoice,
            quoteId: $invoice->quoteId,
            invoiceNumber: $invoice->invoiceNumber,
            issuedAt: $invoice->issuedAt,
            dueAt: $invoice->dueAt,
            notes: $invoice->notes,
            isDeleted: $isDeleted,
            id: $id,
            createdAt: $invoice->createdAt ?? '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );
    }
}
