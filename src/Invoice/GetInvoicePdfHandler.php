<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices/{id}/pdf` — generates and streams the invoice PDF.
 * Capability: ViewBilling (GET on /admin/invoices/* — CapabilityResolver).
 */
final readonly class GetInvoicePdfHandler implements RequestHandlerInterface
{
    public function __construct(
        private GenerateInvoicePdfUseCase $useCase,
        private InvoicePdfGenerator $generator,
        private Psr17Factory $psr17,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Required', 400, 'This action requires an organization context.');
        }

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $pdfData = $this->useCase->execute($organizationId, $id);
        $bytes   = $this->generator->generate($pdfData);

        $invoice  = $pdfData->invoiceWithLines->invoice;
        $filename = $invoice->invoiceNumber !== null
            ? preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoice->invoiceNumber) . '.pdf'
            : 'invoice-' . $id . '.pdf';

        $stream = $this->psr17->createStream($bytes);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withBody($stream);
    }
}
