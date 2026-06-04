<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use DateTimeImmutable;
use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\DocumentSequence\DocumentType;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

/**
 * Issues a draft invoice: validates qualified-invoice requirements, allocates an
 * INV number, and locks it as issued. Compliance: a qualified invoice cannot be
 * issued without an issuer registration number (accounting-compliance §2/§4),
 * and only draft invoices can be issued (issued documents are immutable).
 */
final readonly class IssueInvoiceUseCase
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private CompanySettingsRepositoryInterface $companySettings,
        private DocumentNumberGenerator $numbers,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @throws InvoiceNotFoundException
     * @throws InvoiceValidationException
     * @throws QualifiedInvoiceIncompleteException
     */
    public function execute(?int $actorUserId, int $id, IssueInvoiceInput $input): InvoiceWithLines
    {
        $organizationId = $this->orgId->get();

        $invoice = $this->invoices->findById($id);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($id);
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvoiceValidationException('Only a draft invoice can be issued.');
        }

        $lines = $this->lineItems->findByParent(LineItemParent::Invoice, $id);

        if ($lines === []) {
            throw new InvoiceValidationException('An invoice cannot be issued without line items.');
        }

        $settings = $this->companySettings->find();

        if ($input->qualified) {
            if ($settings === null || $settings->registrationNumber === null) {
                throw new QualifiedInvoiceIncompleteException('A qualified invoice requires the issuer registration number to be configured in company settings.');
            }
        }

        $number = $this->numbers->next(DocumentType::Invoice, (int) date('Y'));

        $issuedAt = date('Y-m-d H:i:s');

        // Due date: explicit input wins, then any pre-set value, else the
        // company payment-terms default (締め日＋支払サイト) from the issue date.
        $dueAt = $input->dueAt ?? $invoice->dueAt;

        if ($dueAt === null) {
            $terms = $settings?->paymentTerms();
            if ($terms !== null) {
                $dueAt = $terms->dueDateFrom(new DateTimeImmutable($issuedAt));
            }
        }

        $this->invoices->update(new Invoice(
            organizationId: $invoice->organizationId,
            clientId: $invoice->clientId,
            status: InvoiceStatus::Issued,
            subtotalCents: $invoice->subtotalCents,
            taxCents: $invoice->taxCents,
            totalCents: $invoice->totalCents,
            isQualifiedInvoice: $input->qualified,
            quoteId: $invoice->quoteId,
            invoiceNumber: $number,
            issuedAt: $issuedAt,
            dueAt: $dueAt,
            notes: $invoice->notes,
            id: $invoice->id,
            createdAt: $invoice->createdAt,
            updatedAt: $invoice->updatedAt,
        ));

        $issued = $this->invoices->findById($id);

        if ($issued === null) {
            throw new LogicException('Invoice disappeared immediately after issue.');
        }

        $this->audit->record($actorUserId, $organizationId, 'invoice.issued', 'invoice', $id, InvoiceResponse::toArray($invoice, $lines), InvoiceResponse::toArray($issued, $lines));

        return new InvoiceWithLines($issued, $lines);
    }
}
