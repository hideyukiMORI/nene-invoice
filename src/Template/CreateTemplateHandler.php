<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/templates` — creates a named template in the caller's organization.
 */
final readonly class CreateTemplateHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateTemplateUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parsed = TemplateField::parse(JsonRequestBodyParser::parse($request));

        $result = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateTemplateInput(name: $parsed['name'], lines: $parsed['lines'], notes: $parsed['notes']),
        );

        return $this->json->create(TemplateResponse::toArray($result->template, $result->lines), 201);
    }
}
