<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/organizations` — superadmin creates a tenant.
 */
final readonly class CreateOrganizationHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateOrganizationUseCase $useCase,
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

        $name = $decoded['name'] ?? null;
        $slug = $decoded['slug'] ?? null;

        if (!is_string($name) || $name === '' || !is_string($slug) || $slug === '') {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Both "name" and "slug" are required.');
        }

        $plan = $decoded['plan'] ?? 'free';
        $plan = is_string($plan) && $plan !== '' ? $plan : 'free';

        $organization = $this->useCase->execute(new CreateOrganizationInput($name, $slug, $plan));

        return $this->json->create(OrganizationResponse::toArray($organization), 201);
    }
}
