<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Error\ProblemDetailsResponseFactory;
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
        private UpdateTemplateUseCase $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $decoded = json_decode((string) $request->getBody(), true);

        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, 'Request body must be a JSON object.');
        }

        $parsed = TemplateField::parse($decoded);
        if ($parsed['error'] !== null) {
            return $this->problemDetails->create($request, 'validation-failed', 'Validation Failed', 422, $parsed['error']);
        }

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            $id,
            new UpdateTemplateInput(name: $parsed['name'], lines: $parsed['lines'], notes: $parsed['notes']),
        );

        return $this->json->create(TemplateResponse::toArray($result->template, $result->lines));
    }
}
