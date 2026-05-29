<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/quotes/{id}` — changes a quote's status (e.g. send, accept).
 */
final readonly class ChangeQuoteStatusHandler implements RequestHandlerInterface
{
    public function __construct(
        private ChangeQuoteStatusUseCase $useCase,
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

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $decoded = json_decode((string) $request->getBody(), true);
        $statusValue = is_array($decoded) ? ($decoded['status'] ?? null) : null;
        $target = is_string($statusValue) ? QuoteStatus::tryFrom($statusValue) : null;

        if ($target === null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"status" must be one of: draft, sent, accepted, rejected, expired.');
        }

        $quote = $this->useCase->execute($organizationId, AuthContext::userId($request), $id, $target);

        return $this->json->create(QuoteResponse::toArray($quote));
    }
}
