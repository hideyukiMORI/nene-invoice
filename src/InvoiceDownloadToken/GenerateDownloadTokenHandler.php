<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/invoices/{id}/download-token`
 * Generates a time-limited public download token for the invoice PDF.
 * Capability: ManageBilling (POST on /admin/invoices/*, via CapabilityResolver).
 */
final readonly class GenerateDownloadTokenHandler implements RequestHandlerInterface
{
    public function __construct(
        private GenerateDownloadTokenUseCase $useCase,
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
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute($organizationId, $id);

        return $this->json->create([
            'url'        => '/invoices/download/' . $result['rawToken'],
            'expires_at' => $result['expiresAt'],
        ], 201);
    }
}
