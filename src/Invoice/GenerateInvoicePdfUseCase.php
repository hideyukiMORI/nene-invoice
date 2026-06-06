<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\Invoice\Pdf\InvoicePdfData;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;

/**
 * Assembles all data needed to render an invoice PDF: invoice with lines,
 * company settings (issuer), and client (buyer).
 */
final readonly class GenerateInvoicePdfUseCase implements GenerateInvoicePdfUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private PaymentRepositoryInterface $payments,
        private CompanySettingsRepositoryInterface $companySettings,
        private ClientRepositoryInterface $clients,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws InvoiceNotFoundException */
    public function execute(int $invoiceId): InvoicePdfData
    {
        $organizationId = $this->orgId->get();

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        $lines       = $this->lineItems->findByParent(LineItemParent::Invoice, $invoiceId);
        $outstanding = max(0, $invoice->totalCents - $this->payments->totalPaidForInvoice($invoiceId));
        $withLines   = new InvoiceWithLines($invoice, $lines, $outstanding);

        $company = $this->companySettings->find()
            ?? new \NeneInvoice\Company\CompanySettings(
                organizationId: $organizationId,
                legalName: '（会社情報未設定）',
            );

        $client = $this->clients->findById($invoice->clientId)
            ?? new \NeneInvoice\Client\Client(
                organizationId: $organizationId,
                name: '（取引先情報なし）',
            );

        return new InvoicePdfData($withLines, $company, $client);
    }
}
