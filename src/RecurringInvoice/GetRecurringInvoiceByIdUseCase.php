<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class GetRecurringInvoiceByIdUseCase
{
    public function __construct(
        private RecurringInvoiceRepositoryInterface $recurring,
        private LineItemRepositoryInterface $lineItems,
    ) {
    }

    /** @throws RecurringInvoiceNotFoundException */
    public function execute(int $id): RecurringInvoiceWithLines
    {
        $schedule = $this->recurring->findById($id);

        if ($schedule === null) {
            throw new RecurringInvoiceNotFoundException($id);
        }

        return new RecurringInvoiceWithLines(
            $schedule,
            $this->lineItems->findByParent(LineItemParent::RecurringInvoice, $id),
        );
    }
}
