<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/clients/{id}` — updates a client in the caller's organization.
 */
final readonly class UpdateClientHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateClientUseCase $useCase,
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

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $name = $decoded['name'] ?? null;

        if (!is_string($name) || $name === '') {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, '"name" is required.');
        }

        $client = $this->useCase->execute($organizationId, $id, new UpdateClientInput(
            name: $name,
            contactName: ClientField::optionalString($decoded, 'contact_name'),
            email: ClientField::optionalString($decoded, 'email'),
            billingAddress: ClientField::optionalString($decoded, 'billing_address'),
            registrationNumber: ClientField::optionalString($decoded, 'registration_number'),
        ));

        return $this->json->create(ClientResponse::toArray($client));
    }
}
