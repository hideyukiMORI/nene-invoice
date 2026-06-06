<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Routing\Router;
use NeneInvoice\Quote\Pdf\QuotePdfGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/quotes/{id}/pdf` — generates and streams the quote PDF.
 * Capability: ViewBilling (GET on /admin/quotes/* — CapabilityResolver).
 */
final readonly class GetQuotePdfHandler implements RequestHandlerInterface
{
    public function __construct(
        private GenerateQuotePdfUseCaseInterface $useCase,
        private QuotePdfGenerator $generator,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $pdfData  = $this->useCase->execute($id);
        $bytes    = $this->generator->generate($pdfData);
        $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $pdfData->quoteWithLines->quote->quoteNumber) . '.pdf';

        $stream = $this->psr17->createStream($bytes);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withBody($stream);
    }
}
