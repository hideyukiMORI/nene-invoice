<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/company-settings` — the issuer profile for the caller's organization.
 * Returns 404 when settings have not been configured yet. The organization is
 * resolved upstream (OrgResolverMiddleware) into the request-scoped holder.
 */
final readonly class GetCompanySettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetCompanySettingsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $settings = $this->useCase->execute();

        return $this->json->create(CompanySettingsResponse::toArray($settings));
    }
}
