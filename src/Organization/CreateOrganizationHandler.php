<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Support\TextLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/organizations` — superadmin creates a tenant.
 */
final readonly class CreateOrganizationHandler implements RequestHandlerInterface
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $useCase,
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
        TextLimit::check($name, 'body.name', TextLimit::NAME);

        $slug = $body['slug'] ?? null;
        if (!is_string($slug) || $slug === '') {
            throw new ValidationException([new ValidationError('body.slug', 'Slug is required.', 'required')]);
        }
        TextLimit::check($slug, 'body.slug', TextLimit::SLUG);

        $plan = $body['plan'] ?? 'free';
        $plan = is_string($plan) && $plan !== '' ? $plan : 'free';
        TextLimit::check($plan, 'body.plan', TextLimit::TINY);

        $organization = $this->useCase->execute(AuthContext::userId($request), new CreateOrganizationInput($name, $slug, $plan));

        return $this->json->create(OrganizationResponse::toArray($organization), 201);
    }
}
