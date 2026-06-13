<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Http\RequestScopedHolder;
use Nene2\Http\SecureTokenHelper;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;
use NeneInvoice\Invoice\InvoiceStatus;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /pay/{token}` — public, no authentication. Renders a minimal page that
 * loads PAY.JP Checkout (hosted card iframe). The card is entered on PAY.JP's
 * iframe, never on a form we control, and this page injects **no operator
 * scripts** (analytics/GTM) — keeping the operator at PCI DSS SAQ-A (ADR 0012).
 *
 * Not-payable links (unknown/expired/revoked/paid/settled) render a 404 page to
 * avoid leaking which links exist.
 */
final readonly class PayPageHandler implements RequestHandlerInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private PaymentLinkRepositoryInterface $links,
        private InvoiceRepositoryInterface $invoices,
        private PaymentRepositoryInterface $payments,
        private \Nene2\Http\ClockInterface $clock,
        private Psr17Factory $psr17,
        private RequestScopedHolder $orgId,
        private string $publicKey,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params   = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $rawToken = is_array($params) && isset($params['token']) ? (string) $params['token'] : '';

        $amount = $this->resolveOutstanding($rawToken);
        if ($amount === null) {
            return $this->html(404, $this->notFoundPage());
        }

        return $this->html(200, $this->payPage($rawToken, $amount));
    }

    /** Returns the outstanding cents to charge, or null when the link is not payable. */
    private function resolveOutstanding(string $rawToken): ?int
    {
        $now  = $this->clock->now()->format('Y-m-d H:i:s');
        $link = $this->links->findByHash(SecureTokenHelper::hash($rawToken));

        if ($link === null || !$link->isPayable($now)) {
            return null;
        }

        $this->orgId->set($link->organizationId);

        $invoice = $this->invoices->findById($link->invoiceId);
        if ($invoice === null || $invoice->id === null
            || !in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
            return null;
        }

        $outstanding = $invoice->totalCents - $this->payments->totalPaidForInvoice($invoice->id);

        return $outstanding > 0 ? $outstanding : null;
    }

    private function payPage(string $rawToken, int $amountCents): string
    {
        $action = '/pay/' . rawurlencode($rawToken) . '/charge';
        $key    = htmlspecialchars($this->publicKey, ENT_QUOTES, 'UTF-8');
        $amount = (string) $amountCents;
        $yen    = htmlspecialchars(number_format($amountCents), ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!doctype html>
            <html lang="ja">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>お支払い</title>
            </head>
            <body>
              <main>
                <h1>カードでお支払い</h1>
                <p>お支払い金額: <strong>¥{$yen}</strong></p>
                <form action="{$action}" method="post">
                  <script
                    src="https://checkout.pay.jp/"
                    class="payjp-button"
                    data-key="{$key}"
                    data-amount="{$amount}"
                    data-currency="jpy"></script>
                </form>
              </main>
            </body>
            </html>
            HTML;
    }

    private function notFoundPage(): string
    {
        return <<<HTML
            <!doctype html>
            <html lang="ja">
            <head><meta charset="utf-8"><title>リンクが無効です</title></head>
            <body><main><h1>お支払いリンクが無効です</h1>
            <p>このお支払いリンクは存在しないか、期限切れ・失効・支払い済みです。</p></main></body>
            </html>
            HTML;
    }

    private function html(int $status, string $body): ResponseInterface
    {
        return $this->psr17->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->psr17->createStream($body));
    }
}
