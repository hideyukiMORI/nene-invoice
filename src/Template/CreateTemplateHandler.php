<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/templates` — creates a named template in the caller's organization.
 */
final readonly class CreateTemplateHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateTemplateUseCase $useCase,
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

        $parsed = TemplateField::parse($decoded);
        if ($parsed['error'] !== null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, $parsed['error']);
        }

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateTemplateInput(name: $parsed['name'], lines: $parsed['lines'], notes: $parsed['notes']),
        );

        return $this->json->create(TemplateResponse::toArray($result->template, $result->lines), 201);
    }
}
