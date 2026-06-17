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

    /** Failed-attempt throttling window and ceiling per source IP (diagnostic F-2). */
    private const THROTTLE_WINDOW_SECONDS = 300;
    private const THROTTLE_MAX_FAILURES = 10;

    public function __construct(
        private UserRepositoryInterface $users,
        private TokenIssuerInterface $tokenIssuer,
        private LoginThrottleInterface $throttle,
        private RefreshTokenIssuer $refreshTokenIssuer,
    ) {
    }

    public function execute(LoginInput $input): LoginOutput
    {
        $ip = $input->ipAddress;

        // Brute-force throttle: once an IP exceeds the failure ceiling within the
        // window, reject further attempts until the window rolls off (429).
        if ($ip !== null) {
            $since = date('Y-m-d H:i:s', time() - self::THROTTLE_WINDOW_SECONDS);
            if ($this->throttle->countFailuresSince($ip, $since) >= self::THROTTLE_MAX_FAILURES) {
                throw new TooManyLoginAttemptsException(self::THROTTLE_WINDOW_SECONDS);
            }
        }

        $user = $this->users->findByEmail($input->email);

        // A failed credential check, or a non-active account (deactivated /
        // not-yet-activated), is rejected with the same generic error so account
        // status is not disclosed (no enumeration). Both count toward the throttle.
        if ($user === null || !password_verify($input->password, $user->passwordHash) || $user->status !== 'active') {
            if ($ip !== null) {
                $this->throttle->recordFailure($ip);
            }

            throw new InvalidCredentialsException();
        }

        if ($ip !== null) {
            $this->throttle->clearFailures($ip);
        }

        $now = time();

        $token = $this->tokenIssuer->issue([
            'sub' => $user->id,
            'role' => $user->role->value,
            'org' => $user->organizationId,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
        ]);

        // Start a fresh refresh-token family for this login (ADR 0014). The
        // plaintext is returned to the handler to seat in the httpOnly cookie.
        $refreshToken = $this->refreshTokenIssuer->issue((int) $user->id, $user->organizationId);

        return new LoginOutput($token, $refreshToken);
    }
}
