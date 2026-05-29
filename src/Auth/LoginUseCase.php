<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Auth\TokenIssuerInterface;
use NeneInvoice\User\UserRepositoryInterface;

/**
 * Authenticates an operator by email + password and issues a bearer token.
 *
 * The token carries the user id (`sub`), role, and organization id so that the
 * auth and capability middleware (later PRs) can authorize requests without a
 * database round-trip on every call.
 */
final readonly class LoginUseCase implements LoginUseCaseInterface
{
    private const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private UserRepositoryInterface $users,
        private TokenIssuerInterface $tokenIssuer,
    ) {
    }

    public function execute(LoginInput $input): LoginOutput
    {
        $user = $this->users->findByEmail($input->email);

        if ($user === null || !password_verify($input->password, $user->passwordHash)) {
            throw new InvalidCredentialsException();
        }

        $now = time();

        $token = $this->tokenIssuer->issue([
            'sub' => $user->id,
            'role' => $user->role->value,
            'org' => $user->organizationId,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
        ]);

        return new LoginOutput($token);
    }
}
