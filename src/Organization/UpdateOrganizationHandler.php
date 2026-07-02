<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Support\TextLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/organizations/{id}` — superadmin updates a tenant. Partial:
 * only the provided fields change. `is_active` suspends/reactivates the tenant;
 * `name` / `plan` update it. A missing organization surfaces as 404 via the
 * domain exception handler.
 */
final readonly class UpdateOrganizationHandler implements RequestHandlerInterface
{
    public function __construct(
        private UpdateOrganizationUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $body = JsonRequestBodyParser::parse($request);

        $name = null;
        $plan = null;
        $isActive = null;
        $provided = false;

        // array_key_exists (not truthiness): `is_active: false` is a real value
        // (suspend), distinct from the key being absent (leave unchanged).
        if (array_key_exists('name', $body)) {
            $raw = $body['name'];
            if (!is_string($raw) || $raw === '') {
                throw new ValidationException([new ValidationError('body.name', 'Name must be a non-empty string.', 'invalid')]);
            }
            TextLimit::check($raw, 'body.name', TextLimit::NAME);
            $name = $raw;
            $provided = true;
        }

        if (array_key_exists('plan', $body)) {
            $raw = $body['plan'];
            if (!is_string($raw) || $raw === '') {
                throw new ValidationException([new ValidationError('body.plan', 'Plan must be a non-empty string.', 'invalid')]);
            }
            TextLimit::check($raw, 'body.plan', TextLimit::TINY);
            $plan = $raw;
            $provided = true;
        }

        if (array_key_exists('is_active', $body)) {
            $raw = $body['is_active'];
            if (!is_bool($raw)) {
                throw new ValidationException([new ValidationError('body.is_active', 'is_active must be a boolean.', 'invalid')]);
            }
            $isActive = $raw;
            $provided = true;
        }

        if (!$provided) {
            throw new ValidationException([new ValidationError('body', 'At least one of name, plan, is_active is required.', 'required')]);
        }

        $organization = $this->useCase->execute(
            AuthContext::userId($request),
            $id,
            new UpdateOrganizationInput($name, $plan, $isActive),
        );

        return $this->json->create(OrganizationResponse::toArray($organization));
    }
}
