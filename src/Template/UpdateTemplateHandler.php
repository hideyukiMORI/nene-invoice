<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/templates/{id}` — updates a template in the caller's organization.
 */
final readonly class UpdateTemplateHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateTemplateUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $parsed = TemplateField::parse(JsonRequestBodyParser::parse($request));

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            $id,
            new UpdateTemplateInput(name: $parsed['name'], lines: $parsed['lines'], notes: $parsed['notes']),
        );

        return $this->json->create(TemplateResponse::toArray($result->template, $result->lines));
    }
}
