<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use NeneInvoice\Support\TextLimit;
use NeneInvoice\User\PasswordPolicy;
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

        [$adminEmail, $adminPassword] = $this->validateInitialAdmin($body);

        $organization = $this->useCase->execute(
            AuthContext::userId($request),
            new CreateOrganizationInput($name, $slug, $plan, $adminEmail, $adminPassword),
        );

        return $this->json->create(OrganizationResponse::toArray($organization), 201);
    }

    /**
     * Validates the optional initial-admin fields. They are both-or-neither: an
     * org may be created alone, or together with its first admin, but supplying
     * only one of `admin_email` / `admin_password` is a 422.
     *
     * @param array<string, mixed> $body
     *
     * @return array{0: ?string, 1: ?string} [adminEmail, adminPassword]
     */
    private function validateInitialAdmin(array $body): array
    {
        $emailRaw    = $body['admin_email'] ?? null;
        $passwordRaw = $body['admin_password'] ?? null;

        $hasEmail    = is_string($emailRaw) && $emailRaw !== '';
        $hasPassword = is_string($passwordRaw) && $passwordRaw !== '';

        if (!$hasEmail && !$hasPassword) {
            return [null, null];
        }

        if ($hasEmail !== $hasPassword) {
            throw new ValidationException([new ValidationError(
                $hasEmail ? 'body.admin_password' : 'body.admin_email',
                'admin_email and admin_password must be provided together.',
                'required',
            )]);
        }

        /** @var string $emailRaw */
        /** @var string $passwordRaw */
        TextLimit::check($emailRaw, 'body.admin_email', TextLimit::NAME);

        if (filter_var($emailRaw, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException([new ValidationError('body.admin_email', 'A valid email is required.', 'invalid')]);
        }

        PasswordPolicy::assert($passwordRaw, 'body.admin_password');

        return [$emailRaw, $passwordRaw];
    }
}
