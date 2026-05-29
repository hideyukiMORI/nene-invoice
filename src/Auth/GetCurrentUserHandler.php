<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/me` — returns the authenticated operator.
 *
 * Reads the verified token claims set by the framework `BearerTokenMiddleware`
 * (attribute `nene2.auth.claims`). Any authenticated user may read their own
 * record, so no capability check is required here.
 */
final readonly class GetCurrentUserHandler implements RequestHandlerInterface
{
    private const CLAIMS_ATTRIBUTE = 'nene2.auth.claims';

    public function __construct(
        private GetCurrentUserUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $claims = $request->getAttribute(self::CLAIMS_ATTRIBUTE);
        $userId = is_array($claims) && isset($claims['sub']) && is_int($claims['sub']) ? $claims['sub'] : 0;

        $user = $userId > 0 ? $this->useCase->execute($userId) : null;

        if ($user === null) {
            return $this->problemDetails->create($request, 'unauthorized', 'Unauthorized', 401, 'The authenticated user no longer exists.');
        }

        return $this->json->create([
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'organization_id' => $user->organizationId,
        ]);
    }
}
