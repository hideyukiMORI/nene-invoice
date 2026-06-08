<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/service-tokens` — issues a NeNe Clear service token for the
 * caller's organization. The plaintext token is returned **once** in the 201
 * response and never again. Requires ManageUsers (admin oversight).
 */
final readonly class IssueServiceTokenHandler implements RequestHandlerInterface
{
    public function __construct(
        private IssueServiceTokenUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $input = ServiceTokenField::parse(JsonRequestBodyParser::parse($request));

        $result = $this->useCase->execute(AuthContext::userId($request), $input);

        return $this->json->create(ServiceTokenResponse::toCreatedArray($result), 201);
    }
}
