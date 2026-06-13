<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `DELETE /admin/company-settings/seal` — removes the organization's seal (社印).
 * Idempotent: succeeds whether or not a seal was set.
 */
final readonly class DeleteCompanySealHandler implements RequestHandlerInterface
{
    public function __construct(
        private CompanySealUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->useCase->delete(AuthContext::userId($request));

        return $this->json->create(['has_seal' => false]);
    }
}
