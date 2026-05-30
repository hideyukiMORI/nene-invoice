<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\InvoiceDownloadToken;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\InvoiceDownloadToken\GenerateDownloadTokenUseCase;
use NeneInvoice\Tests\Support\InMemoryInvoiceDownloadTokenRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use PHPUnit\Framework\TestCase;

final class GenerateDownloadTokenUseCaseTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private InMemoryInvoiceDownloadTokenRepository $tokens;

    private GenerateDownloadTokenUseCase $useCase;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;

    protected function setUp(): void
    {
        $this->holder   = new RequestScopedHolder();
        $this->holder->set(1);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->tokens   = new InMemoryInvoiceDownloadTokenRepository();
        $this->useCase  = new GenerateDownloadTokenUseCase($this->invoices, $this->tokens, $this->holder);
    }

    public function test_generates_token_for_invoice_in_org(): void
    {
        $id = $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 100, totalCents: 1100));

        $result = $this->useCase->execute($id);

        self::assertNotEmpty($result['rawToken']);
        self::assertNotEmpty($result['expiresAt']);

        // Token is stored hashed
        $stored = $this->tokens->findByHash(hash('sha256', $result['rawToken']));
        self::assertNotNull($stored);
        self::assertSame($id, $stored->invoiceId);
    }

    public function test_throws_for_wrong_org(): void
    {
        // Invoice belongs to org 2; the holder resolves org 1, so it is invisible.
        $id = $this->invoices->save(new Invoice(organizationId: 2, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));

        $this->expectException(InvoiceNotFoundException::class);
        $this->useCase->execute($id);
    }

    public function test_raw_token_is_url_safe_base64(): void
    {
        $id     = $this->invoices->save(new Invoice(organizationId: 1, clientId: 1, status: InvoiceStatus::Issued, subtotalCents: 1000, taxCents: 0, totalCents: 1000));
        $result = $this->useCase->execute($id);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $result['rawToken']);
    }
}
