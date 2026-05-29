<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Invoice;

use NeneInvoice\Company\CompanySettings;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Invoice\InvoiceValidationException;
use NeneInvoice\Invoice\IssueInvoiceInput;
use NeneInvoice\Invoice\IssueInvoiceUseCase;
use NeneInvoice\Invoice\QualifiedInvoiceIncompleteException;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\Tests\Support\InMemoryCompanySettingsRepository;
use NeneInvoice\Tests\Support\InMemoryDocumentSequenceRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class IssueInvoiceUseCaseTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryCompanySettingsRepository $companySettings;
    private RecordingAuditRecorder $audit;
    private IssueInvoiceUseCase $useCase;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository();
        $this->lineItems = new InMemoryLineItemRepository();
        $this->companySettings = new InMemoryCompanySettingsRepository();
        $this->audit = new RecordingAuditRecorder();
        $this->useCase = new IssueInvoiceUseCase(
            $this->invoices,
            $this->lineItems,
            $this->companySettings,
            new DocumentNumberGenerator(new InMemoryDocumentSequenceRepository()),
            $this->audit,
        );
    }

    private function draftInvoice(bool $withLines = true): int
    {
        $id = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 5,
            status: InvoiceStatus::Draft,
            subtotalCents: 2000,
            taxCents: 200,
            totalCents: 2200,
        ));

        if ($withLines) {
            $this->lineItems->replaceForParent(LineItemParent::Invoice, $id, [
                new LineItem(LineItemParent::Invoice, $id, 'Std', 1, 2000, 1000, sortOrder: 0),
            ]);
        }

        return $id;
    }

    private function registerIssuer(): void
    {
        $this->companySettings->save(new CompanySettings(
            organizationId: 1,
            legalName: 'Example KK',
            registrationNumber: 'T1234567890123',
        ));
    }

    public function test_issues_qualified_invoice_allocating_number_and_auditing(): void
    {
        $this->registerIssuer();
        $id = $this->draftInvoice();

        $result = $this->useCase->execute(1, 7, $id, new IssueInvoiceInput(qualified: true));

        self::assertSame(InvoiceStatus::Issued, $result->invoice->status);
        self::assertTrue($result->invoice->isQualifiedInvoice);
        self::assertSame('INV-' . date('Y') . '-001', $result->invoice->invoiceNumber);
        self::assertNotNull($result->invoice->issuedAt);
        self::assertSame('invoice.issued', $this->audit->records[0]['action']);
    }

    public function test_issues_non_qualified_invoice_without_registration_number(): void
    {
        $id = $this->draftInvoice();

        $result = $this->useCase->execute(1, 7, $id, new IssueInvoiceInput(qualified: false));

        self::assertSame(InvoiceStatus::Issued, $result->invoice->status);
        self::assertFalse($result->invoice->isQualifiedInvoice);
        self::assertSame('INV-' . date('Y') . '-001', $result->invoice->invoiceNumber);
    }

    public function test_qualified_invoice_without_registration_number_is_rejected(): void
    {
        $id = $this->draftInvoice();

        $this->expectException(QualifiedInvoiceIncompleteException::class);
        $this->useCase->execute(1, 7, $id, new IssueInvoiceInput(qualified: true));
    }

    public function test_only_draft_invoice_can_be_issued(): void
    {
        $this->registerIssuer();
        $id = $this->draftInvoice();
        $this->useCase->execute(1, 7, $id, new IssueInvoiceInput(qualified: true));

        $this->expectException(InvoiceValidationException::class);
        $this->useCase->execute(1, 7, $id, new IssueInvoiceInput(qualified: true));
    }

    public function test_invoice_without_line_items_cannot_be_issued(): void
    {
        $this->registerIssuer();
        $id = $this->draftInvoice(withLines: false);

        $this->expectException(InvoiceValidationException::class);
        $this->useCase->execute(1, 7, $id, new IssueInvoiceInput(qualified: true));
    }

    public function test_cross_organization_invoice_not_found(): void
    {
        $this->registerIssuer();
        $id = $this->draftInvoice();

        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute(2, 7, $id, new IssueInvoiceInput(qualified: true));
    }
}
