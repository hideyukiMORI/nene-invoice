<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\BankTransaction;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Routing\Router;
use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionDirection;
use NeneInvoice\BankTransaction\BankTransactionNotFoundException;
use NeneInvoice\BankTransaction\BankTransactionStatus;
use NeneInvoice\BankTransaction\BankTransactionSuggestionsHandler;
use NeneInvoice\BankTransaction\BankTransactionValidationException;
use NeneInvoice\BankTransaction\ConfirmBankTransactionMatchHandler;
use NeneInvoice\BankTransaction\ConfirmMatchUseCase;
use NeneInvoice\BankTransaction\IgnoreBankTransactionHandler;
use NeneInvoice\BankTransaction\IgnoreBankTransactionUseCase;
use NeneInvoice\BankTransaction\ImportBankTransactionsHandler;
use NeneInvoice\BankTransaction\ImportBankTransactionsUseCase;
use NeneInvoice\BankTransaction\ListBankTransactionsHandler;
use NeneInvoice\BankTransaction\ListBankTransactionsUseCase;
use NeneInvoice\BankTransaction\OpenReceivable;
use NeneInvoice\BankTransaction\SuggestMatchesUseCase;
use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\RecordPaymentUseCase;
use NeneInvoice\Tests\Support\FixedClock;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryBankTransactionRepository;
use NeneInvoice\Tests\Support\InMemoryInvoiceRepository;
use NeneInvoice\Tests\Support\InMemoryMatchCandidateRepository;
use NeneInvoice\Tests\Support\InMemoryPayerAliasRepository;
use NeneInvoice\Tests\Support\InMemoryPaymentRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP-layer coverage for the bank-reconciliation handlers (#505), wiring the real
 * use cases against in-memory repositories. Domain exceptions propagate to the
 * error middleware (mapped to 422 / 404 there), so tests assert on them.
 */
final class BankTransactionHandlersTest extends TestCase
{
    private Psr17Factory $psr17;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryBankTransactionRepository $bank;
    private InMemoryPayerAliasRepository $aliases;
    private InMemoryPaymentRepository $payments;
    private InMemoryInvoiceRepository $invoices;
    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->psr17   = new Psr17Factory();
        $this->holder  = new RequestScopedHolder();
        $this->holder->set(1);
        $this->bank     = new InMemoryBankTransactionRepository($this->holder);
        $this->aliases  = new InMemoryPayerAliasRepository($this->holder);
        $this->payments = new InMemoryPaymentRepository($this->holder);
        $this->invoices = new InMemoryInvoiceRepository($this->holder);
        $this->audit    = new RecordingAuditRecorder();
    }

    public function test_import_stages_rows_and_returns_200(): void
    {
        $csv = "日付,金額,依頼人,摘要\n2026-06-05,11000,サンプル,振込\n";

        $response = $this->importHandler()->handle($this->importRequest($csv));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertSame(1, $body['imported_count']);
        self::assertNull($body['format_error']);
        self::assertSame(1, $this->bank->countByOrganization());
    }

    public function test_import_unknown_preset_is_rejected(): void
    {
        $this->expectException(BankTransactionValidationException::class);
        $this->importHandler()->handle($this->importRequest("日付,金額\n2026-06-05,1\n", 'nope'));
    }

    public function test_list_returns_pagination_envelope_and_filters_by_status(): void
    {
        $this->stage(BankTransactionStatus::Unmatched, 'TX-U');
        $this->stage(BankTransactionStatus::Ignored, 'TX-I');

        $all = $this->decode($this->listHandler()->handle($this->get('/admin/bank-transactions'))->getBody());
        self::assertSame(2, $all['total']);
        self::assertSame(20, $all['limit']);
        self::assertCount(2, $all['items']);

        $unmatched = $this->decode(
            $this->listHandler()->handle($this->get('/admin/bank-transactions', ['status' => 'unmatched']))->getBody(),
        );
        self::assertSame(1, $unmatched['total']);
        self::assertSame('unmatched', $unmatched['items'][0]['status']);
    }

    public function test_list_rejects_unknown_status(): void
    {
        $this->expectException(BankTransactionValidationException::class);
        $this->listHandler()->handle($this->get('/admin/bank-transactions', ['status' => 'bogus']));
    }

    public function test_suggestions_returns_ranked_items(): void
    {
        $id = $this->stage(BankTransactionStatus::Unmatched, 'TX-S', 11000, 'サンプル');

        $handler = $this->suggestionsHandler([
            new OpenReceivable(invoiceId: 10, clientId: 7, outstandingCents: 11000, invoiceNumber: 'INV-10', clientName: 'サンプル', clientNameKana: 'サンプル'),
        ]);

        $response = $handler->handle($this->getWithId('/admin/bank-transactions/' . $id . '/suggestions', $id));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertNotEmpty($body['items']);
        self::assertSame(10, $body['items'][0]['invoice_id']);
        self::assertContains('amount-exact', $body['items'][0]['reasons']);
    }

    public function test_suggestions_unknown_id_throws_not_found(): void
    {
        $this->expectException(BankTransactionNotFoundException::class);
        $this->suggestionsHandler()->handle($this->getWithId('/admin/bank-transactions/999/suggestions', 999));
    }

    public function test_confirm_records_payment_and_posts_line(): void
    {
        $invoiceId = $this->invoices->save(new Invoice(
            organizationId: 1,
            clientId: 7,
            status: InvoiceStatus::Issued,
            subtotalCents: 11000,
            taxCents: 0,
            totalCents: 11000,
            invoiceNumber: 'INV-2026-001',
            issuedAt: '2026-05-29 00:00:00',
        ));
        $txnId = $this->stage(BankTransactionStatus::Unmatched, 'TX-C', 11000, 'サンプル');

        $response = $this->confirmHandler()->handle(
            $this->postWithId('/admin/bank-transactions/' . $txnId . '/confirm', $txnId, ['invoice_id' => $invoiceId]),
        );

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertSame('posted', $body['transaction']['status']);
        self::assertSame($invoiceId, $body['transaction']['matched_invoice_id']);
        self::assertSame('paid', $body['payment']['invoice_status']);
        self::assertSame(11000, $body['payment']['total_paid_cents']);
    }

    public function test_confirm_without_invoice_id_is_rejected(): void
    {
        $txnId = $this->stage(BankTransactionStatus::Unmatched, 'TX-C2', 11000, 'サンプル');

        $this->expectException(BankTransactionValidationException::class);
        $this->confirmHandler()->handle($this->postWithId('/admin/bank-transactions/' . $txnId . '/confirm', $txnId, ['note' => 'no invoice id']));
    }

    public function test_ignore_marks_line_ignored(): void
    {
        $txnId = $this->stage(BankTransactionStatus::Unmatched, 'TX-IG', 11000, 'サンプル');

        $response = $this->ignoreHandler()->handle($this->postWithId('/admin/bank-transactions/' . $txnId . '/ignore', $txnId, []));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ignored', $this->decode($response->getBody())['status']);
    }

    private function stage(BankTransactionStatus $status, string $reference, int $amountCents = 11000, ?string $payerName = 'サンプル'): int
    {
        return $this->bank->save(new BankTransaction(
            organizationId: 1,
            valueDate: '2026-06-05',
            direction: BankTransactionDirection::Credit,
            amountCents: $amountCents,
            payerName: $payerName,
            bankReference: $reference,
            status: $status,
        ));
    }

    private function importHandler(): ImportBankTransactionsHandler
    {
        return new ImportBankTransactionsHandler(
            new ImportBankTransactionsUseCase(new ImmediateTransactionManager(), fn () => $this->bank),
            $this->json(),
        );
    }

    private function listHandler(): ListBankTransactionsHandler
    {
        return new ListBankTransactionsHandler(new ListBankTransactionsUseCase($this->bank), $this->json());
    }

    /** @param list<OpenReceivable> $receivables */
    private function suggestionsHandler(array $receivables = []): BankTransactionSuggestionsHandler
    {
        return new BankTransactionSuggestionsHandler(
            new SuggestMatchesUseCase($this->bank, new InMemoryMatchCandidateRepository($receivables), $this->aliases),
            $this->json(),
        );
    }

    private function confirmHandler(): ConfirmBankTransactionMatchHandler
    {
        $recordPayment = new RecordPaymentUseCase(
            $this->payments,
            $this->invoices,
            new ImmediateTransactionManager(),
            fn () => $this->payments,
            fn () => $this->invoices,
            $this->audit,
            new FixedClock(),
            $this->holder,
        );

        return new ConfirmBankTransactionMatchHandler(
            new ConfirmMatchUseCase($this->bank, $this->aliases, $recordPayment),
            $this->json(),
        );
    }

    private function ignoreHandler(): IgnoreBankTransactionHandler
    {
        return new IgnoreBankTransactionHandler(new IgnoreBankTransactionUseCase($this->bank), $this->json());
    }

    private function json(): JsonResponseFactory
    {
        return new JsonResponseFactory($this->psr17, $this->psr17);
    }

    private function importRequest(string $csv, string $preset = 'signed_amount'): ServerRequestInterface
    {
        return $this->base('POST', '/admin/bank-transactions/import')
            ->withQueryParams(['preset' => $preset])
            ->withHeader('Content-Type', 'text/csv')
            ->withBody($this->psr17->createStream($csv));
    }

    /** @param array<string, string> $query */
    private function get(string $path, array $query = []): ServerRequestInterface
    {
        $request = $this->base('GET', $path);

        return $query === [] ? $request : $request->withQueryParams($query);
    }

    private function getWithId(string $path, int $id): ServerRequestInterface
    {
        return $this->base('GET', $path)->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $id]);
    }

    /** @param array<string, mixed> $body */
    private function postWithId(string $path, int $id, array $body): ServerRequestInterface
    {
        return $this->base('POST', $path)
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $id])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
    }

    private function base(string $method, string $path): ServerRequestInterface
    {
        return $this->psr17->createServerRequest($method, $path)
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'org' => 1, 'role' => 'admin']);
    }

    /** @return array<string, mixed> */
    private function decode(\Psr\Http\Message\StreamInterface $body): array
    {
        $decoded = json_decode((string) $body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
