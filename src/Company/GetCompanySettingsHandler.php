<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/company-settings` — the issuer profile for the caller's organization.
 * Returns 404 when settings have not been configured yet.
 */
final readonly class GetCompanySettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetCompanySettingsUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'organization-not-resolved', 'Organization Required', 400, 'This action requires an organization context.');
        }

        $settings = $this->useCase->execute($organizationId);

        return $this->json->create(CompanySettingsResponse::toArray($settings));
    }
}
