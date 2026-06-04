<?php

declare(strict_types=1);

namespace NeneInvoice\Audit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/audit-logs/export` — exports the (filtered) audit trail as a CSV
 * file. Same organization scoping and `manage_users` gate as the list endpoint;
 * accepts the same filter query parameters.
 */
final readonly class ExportAuditLogsCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ExportAuditLogsCsvUseCase $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $filter   = AuditLogFilterFactory::fromQueryParams($request->getQueryParams());
        $csv      = $this->useCase->execute($filter);
        $filename = 'audit-logs-' . date('Y-m-d') . '.csv';
        $stream   = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
