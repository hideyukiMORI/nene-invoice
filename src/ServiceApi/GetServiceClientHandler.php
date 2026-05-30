<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Client\ClientRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /api/clients/{id}` — returns client contact data for the service caller's
 * organization (contract §2.3). Used by NeNe Clear for dunning (督促) to resolve
 * the billing contact name and recipient email from a `client_id` on an invoice.
 *
 * `recipient_email` maps to `clients.email` — the same field the operator sees
 * as "email" in the admin UI, renamed to match the upstream contract.
 */
final readonly class GetServiceClientHandler implements RequestHandlerInterface
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = ServiceAuthContext::organizationId($request);

        if ($organizationId === null) {
            return $this->problemDetails->create($request, 'insufficient-scope', 'Forbidden', 403, 'The service token is not scoped to an organization.');
        }

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $client = $this->clients->findById($id);

        if ($client === null || $client->organizationId !== $organizationId || $client->isDeleted) {
            return $this->problemDetails->create($request, 'invoice-not-found', 'Not Found', 404, 'Client not found.');
        }

        return $this->json->create([
            'id'                  => $client->id,
            'name'                => $client->name,
            'contact_name'        => $client->contactName,
            'recipient_email'     => $client->email,
            'billing_address'     => $client->billingAddress,
            'registration_number' => $client->registrationNumber,
        ]);
    }
}
