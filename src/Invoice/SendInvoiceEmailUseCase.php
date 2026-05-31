<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Mailer\MailerInterface;
use NeneInvoice\Mailer\MailMessage;

/**
 * Sends a PDF of an issued invoice to the client's email address.
 *
 * Only issued / partially_paid invoices can be sent (draft has no number).
 * Requires the client to have an email address.
 */
final readonly class SendInvoiceEmailUseCase
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private LineItemRepositoryInterface $lineItems,
        private ClientRepositoryInterface $clients,
        private CompanySettingsRepositoryInterface $companySettings,
        private InvoicePdfGenerator $pdfGenerator,
        private MailerInterface $mailer,
        private RequestScopedHolder $orgId,
        private string $fromName,
    ) {
    }

    /**
     * @throws InvoiceNotFoundException
     * @throws InvoiceEmailException
     */
    public function execute(int $invoiceId): void
    {
        $organizationId = $this->orgId->get();

        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        if (
            $invoice->status !== InvoiceStatus::Issued
            && $invoice->status !== InvoiceStatus::PartiallyPaid
            && $invoice->status !== InvoiceStatus::Paid
        ) {
            throw InvoiceEmailException::notIssued($invoiceId);
        }

        $client = $this->clients->findById($invoice->clientId)
            ?? new Client(organizationId: $organizationId, name: '');

        if ($client->email === null || $client->email === '') {
            throw InvoiceEmailException::noClientEmail($invoiceId);
        }

        $lines     = $this->lineItems->findByParent(LineItemParent::Invoice, $invoiceId);
        $withLines = new InvoiceWithLines($invoice, $lines, 0);

        $company = $this->companySettings->find()
            ?? new CompanySettings(organizationId: $organizationId, legalName: $this->fromName);

        $pdfData = new Pdf\InvoicePdfData($withLines, $company, $client);
        $pdfBytes = $this->pdfGenerator->generate($pdfData);

        $invoiceNumber = $invoice->invoiceNumber ?? "INV-{$invoiceId}";
        $companyName   = $company->legalName;

        $bodyHtml = sprintf(
            '<p>%s 様</p><p>%s より請求書 %s をお送りします。</p><p>添付の PDF をご確認ください。</p><p>--<br>%s</p>',
            htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'),
        );

        $this->mailer->send(new MailMessage(
            toAddress: $client->email,
            toName: $client->name,
            subject: "請求書 {$invoiceNumber} — {$companyName}",
            bodyHtml: $bodyHtml,
            attachmentBytes: $pdfBytes,
            attachmentName: preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceNumber) . '.pdf',
            attachmentMime: 'application/pdf',
        ));
    }
}
