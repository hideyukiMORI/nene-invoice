<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/templates/{id}` — reads one template (with line presets) in the
 * resolved organization; cross-org or missing ids return 404.
 */
final readonly class GetTemplateByIdHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetTemplateByIdUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute($id);

        return $this->json->create(TemplateResponse::toArray($result->template, $result->lines));
    }
}
