<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/clients` — creates a client in the caller's organization.
 */
final readonly class CreateClientHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateClientUseCase $useCase,
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

        if (!is_string($name) || $name === '') {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"name" is required.');
        }

        $client = $this->useCase->execute(AuthContext::userId($request), new CreateClientInput(
            name: $name,
            contactName: ClientField::optionalString($decoded, 'contact_name'),
            email: ClientField::optionalString($decoded, 'email'),
            billingAddress: ClientField::optionalString($decoded, 'billing_address'),
            registrationNumber: ClientField::optionalString($decoded, 'registration_number'),
        ));

        return $this->json->create(ClientResponse::toArray($client), 201);
    }
}
