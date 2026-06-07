<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/items/import-template` — downloads the items import template
 * (header only). Requires ViewBilling capability.
 */
final readonly class GetItemsImportTemplateHandler implements RequestHandlerInterface
{
    public function __construct(private Psr17Factory $psr17)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csv    = ItemImportTemplate::csv();
        $stream = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="items-import-template.csv"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
