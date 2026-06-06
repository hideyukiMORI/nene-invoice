<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `DELETE /admin/templates/{id}` — soft-deletes a template in the resolved
 * organization (scoped by the repository via the request-scoped org holder).
 */
final readonly class DeleteTemplateHandler implements RequestHandlerInterface
{
    public function __construct(
        private DeleteTemplateUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $this->useCase->execute(AuthContext::userId($request), $id);

        return $this->json->createEmpty(204);
    }
}
