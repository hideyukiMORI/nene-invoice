<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

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
 * The organization is resolved upstream (OrgResolverMiddleware) into the holder.
 */
final readonly class GenerateDownloadTokenHandler implements RequestHandlerInterface
{
    public function __construct(
        private GenerateDownloadTokenUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute(AuthContext::userId($request), $id);

        return $this->json->create([
            'url'        => '/invoices/download/' . $result['rawToken'],
            'expires_at' => $result['expiresAt'],
        ], 201);
    }
}
