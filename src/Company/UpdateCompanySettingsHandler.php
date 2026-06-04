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

        $rangeError = $this->validateBillingDefaults($decoded);

        if ($rangeError !== null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, $rangeError);
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
            defaultQuoteValidityDays: $this->optionalInt($decoded, 'default_quote_validity_days'),
            defaultPaymentClosingDay: $this->optionalInt($decoded, 'default_payment_closing_day'),
            defaultPaymentMonthOffset: $this->optionalInt($decoded, 'default_payment_month_offset'),
            defaultPaymentPayDay: $this->optionalInt($decoded, 'default_payment_pay_day'),
        ));

        return $this->json->create(CompanySettingsResponse::toArray($settings));
    }

    /** @param array<string, mixed> $body */
    private function optional(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $body */
    private function optionalInt(array $body, string $key): ?int
    {
        $value = $body[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * Validates the billing-default integers (Issue #268). Returns an error
     * message, or null when all are absent / null / valid.
     *
     * @param array<string, mixed> $body
     */
    private function validateBillingDefaults(array $body): ?string
    {
        if (!$this->isNullOrIntInRange($body['default_quote_validity_days'] ?? null, 1, 3650)) {
            return '"default_quote_validity_days" must be an integer between 1 and 3650.';
        }
        if (!$this->isNullOrIntInRange($body['default_payment_closing_day'] ?? null, 1, 31)) {
            return '"default_payment_closing_day" must be an integer between 1 and 31.';
        }
        if (!$this->isNullOrIntInRange($body['default_payment_month_offset'] ?? null, 0, 12)) {
            return '"default_payment_month_offset" must be an integer between 0 and 12.';
        }
        if (!$this->isNullOrIntInRange($body['default_payment_pay_day'] ?? null, 1, 31)) {
            return '"default_payment_pay_day" must be an integer between 1 and 31.';
        }

        return null;
    }

    private function isNullOrIntInRange(mixed $value, int $min, int $max): bool
    {
        if ($value === null) {
            return true;
        }

        return is_int($value) && $value >= $min && $value <= $max;
    }
}
