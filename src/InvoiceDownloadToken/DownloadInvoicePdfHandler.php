<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

use DateTimeImmutable;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\SecureTokenHelper;
use Nene2\Routing\Router;
use NeneInvoice\Invoice\GenerateInvoicePdfUseCase;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /invoices/download/{token}` — public PDF download, no authentication.
 * Validates the token (existence + expiry + invoice not deleted), then streams
 * the PDF. Expired or invalid tokens yield 404 to avoid oracle attacks.
 *
 * This route bypasses OrgResolverMiddleware, so the org holder is unset on entry.
 * The validated token record carries the organization; this handler sets the
 * holder from it before invoking the (org-scoped) PDF use case (ADR 0006).
 */
final readonly class DownloadInvoicePdfHandler implements RequestHandlerInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private InvoiceDownloadTokenRepositoryInterface $tokens,
        private GenerateInvoicePdfUseCase $pdfData,
        private InvoicePdfGenerator $pdfGenerator,
        private Psr17Factory $psr17,
        private ProblemDetailsResponseFactory $problemDetails,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params    = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $rawToken  = is_array($params) && isset($params['token']) ? (string) $params['token'] : '';
        $tokenHash = SecureTokenHelper::hash($rawToken);

        $record = $this->tokens->findByHash($tokenHash);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($record === null || $record->isExpired($now)) {
            return $this->problemDetails->create($request, 'invoice-not-found', 'Not Found', 404, 'Download link not found or expired.');
        }

        // Public route (no OrgResolver): scope the org from the validated token.
        $this->orgId->set($record->organizationId);

        try {
            $pdfData = $this->pdfData->execute($record->invoiceId);
        } catch (InvoiceNotFoundException) {
            return $this->problemDetails->create($request, 'invoice-not-found', 'Not Found', 404, 'Invoice not found.');
        }

        $bytes    = $this->pdfGenerator->generate($pdfData);
        $invoice  = $pdfData->invoiceWithLines->invoice;
        $filename = $invoice->invoiceNumber !== null
            ? preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoice->invoiceNumber) . '.pdf'
            : 'invoice-' . $record->invoiceId . '.pdf';

        $stream = $this->psr17->createStream($bytes);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withBody($stream);
    }
}
