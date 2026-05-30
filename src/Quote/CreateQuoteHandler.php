<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\LineItem\LineItemInput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/quotes` — creates a draft quote in the caller's organization.
 */
final readonly class CreateQuoteHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateQuoteUseCase $useCase,
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

        $clientId = $decoded['client_id'] ?? null;

        if (!is_int($clientId) && !(is_string($clientId) && ctype_digit($clientId))) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"client_id" is required.');
        }

        $rawLines = $decoded['line_items'] ?? null;

        if (!is_array($rawLines) || $rawLines === []) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"line_items" must be a non-empty array.');
        }

        $lines = [];
        foreach ($rawLines as $raw) {
            if (!is_array($raw)) {
                return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Each line item must be an object.');
            }

            $description = $raw['description'] ?? null;
            $quantity = $raw['quantity'] ?? null;
            $unitPrice = $raw['unit_price_cents'] ?? null;
            $taxRate = $raw['tax_rate_bps'] ?? null;

            if (!is_string($description) || $description === '' || !is_int($quantity) || !is_int($unitPrice) || !is_int($taxRate)) {
                return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Each line item needs description (string) and quantity / unit_price_cents / tax_rate_bps (integers).');
            }

            $lines[] = new LineItemInput($description, $quantity, $unitPrice, $taxRate);
        }

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateQuoteInput(
                clientId: (int) $clientId,
                lines: $lines,
                validUntil: $this->optional($decoded, 'valid_until'),
                notes: $this->optional($decoded, 'notes'),
            ),
        );

        return $this->json->create(QuoteResponse::toArray($result->quote, $result->lines), 201);
    }

    /** @param array<string, mixed> $body */
    private function optional(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
