<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

/**
 * A recurring-billing schedule (#503): a reusable template that generates an
 * invoice every period (顧問料・保守費・管理料・月謝 等). The line template lives in
 * `line_items` (polymorphic) and is attached/loaded separately, mirroring how
 * quotes and invoices keep their rows — totals here are integer cents (ADR 0004),
 * computed from the template lines by the create use case (a later PR).
 *
 * `nextRunOn` / `lastRunOn` are calendar dates (`Y-m-d`), like `valid_until`.
 * This entity only persists the schedule; generating draft invoices and issuing
 * them are separate, compliance-reviewed concerns.
 */
final readonly class RecurringInvoice
{
    public function __construct(
        public int $organizationId,
        public int $clientId,
        public string $name,
        public RecurringFrequency $frequency,
        public int $subtotalCents,
        public int $taxCents,
        public int $totalCents,
        public string $nextRunOn,
        public ?string $lastRunOn = null,
        public bool $isActive = true,
        public ?string $notes = null,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
