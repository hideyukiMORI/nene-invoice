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
 * `PUT /admin/company-settings` — upserts the issuer profile for the caller's
 * organization.
 */
final readonly class UpdateCompanySettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateCompanySettingsUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $decoded = json_decode((string) $request->getBody(), true);

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $legalName = $decoded['legal_name'] ?? null;

        if (!is_string($legalName) || $legalName === '') {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"legal_name" is required.');
        }

        $settings = $this->useCase->execute(AuthContext::userId($request), new UpdateCompanySettingsInput(
            legalName: $legalName,
            address: $this->optional($decoded, 'address'),
            phone: $this->optional($decoded, 'phone'),
            email: $this->optional($decoded, 'email'),
            registrationNumber: $this->optional($decoded, 'registration_number'),
            bankName: $this->optional($decoded, 'bank_name'),
            bankBranch: $this->optional($decoded, 'bank_branch'),
            accountType: $this->optional($decoded, 'account_type'),
            accountNumber: $this->optional($decoded, 'account_number'),
            logoUrl: $this->optional($decoded, 'logo_url'),
        ));

        return $this->json->create(CompanySettingsResponse::toArray($settings));
    }

    /** @param array<string, mixed> $body */
    private function optional(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
