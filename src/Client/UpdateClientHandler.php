<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Support\RequestField;
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
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $body = JsonRequestBodyParser::parse($request);

        $name = $body['name'] ?? null;

        if (!is_string($name) || $name === '') {
            throw new ValidationException([new ValidationError('body.name', 'Name is required.', 'required')]);
        }

        $client = $this->useCase->execute(AuthContext::userId($request), $id, new UpdateClientInput(
            name: $name,
            nameKana: RequestField::optionalString($body, 'name_kana'),
            contactName: RequestField::optionalString($body, 'contact_name'),
            email: RequestField::optionalString($body, 'email'),
            billingAddress: RequestField::optionalString($body, 'billing_address'),
            registrationNumber: RequestField::optionalString($body, 'registration_number'),
        ));

        return $this->json->create(ClientResponse::toArray($client));
    }
}
