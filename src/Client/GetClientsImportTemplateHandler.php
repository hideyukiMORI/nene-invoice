<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/clients/import-template` — downloads the clients import template
 * (header only). The user adds rows beneath it and uploads to the import
 * endpoint. Requires ViewBilling capability.
 */
final readonly class GetClientsImportTemplateHandler implements RequestHandlerInterface
{
    public function __construct(private Psr17Factory $psr17)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csv    = ClientImportTemplate::csv();
        $stream = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="clients-import-template.csv"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
