<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Support\RequestField;
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
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $name = $body['name'] ?? null;

        if (!is_string($name) || $name === '') {
            throw new ValidationException([new ValidationError('body.name', 'Name is required.', 'required')]);
        }

        $client = $this->useCase->execute(AuthContext::userId($request), new CreateClientInput(
            name: $name,
            nameKana: RequestField::optionalString($body, 'name_kana'),
            contactName: RequestField::optionalString($body, 'contact_name'),
            email: RequestField::optionalString($body, 'email'),
            billingAddress: RequestField::optionalString($body, 'billing_address'),
            registrationNumber: RequestField::optionalString($body, 'registration_number'),
        ));

        return $this->json->create(ClientResponse::toArray($client), 201);
    }
}
