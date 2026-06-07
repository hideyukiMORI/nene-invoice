<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/clients/import` — imports clients from a template CSV uploaded as
 * the raw `text/csv` request body. `?dry_run=1` validates and returns the same
 * report without writing. Accepted imports return 200; rejected (format or row
 * errors) return 422 with a per-row report. Requires ManageBilling capability.
 */
final readonly class ImportClientsCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ImportClientsCsvUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $raw    = (string) $request->getBody();
        $dryRun = ($request->getQueryParams()['dry_run'] ?? null) === '1';

        $result = $this->useCase->execute(AuthContext::userId($request), $raw, $dryRun);

        return $this->json->create($result->toArray(), $result->accepted ? 200 : 422);
    }
}
