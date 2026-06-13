<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PUT /admin/company-settings/seal` — upserts the organization's seal (社印)
 * from a base64 PNG in the JSON body (`image_base64`). Invalid images raise a
 * 422 `validation-failed` via {@see SealImageValidator}.
 */
final readonly class PutCompanySealHandler implements RequestHandlerInterface
{
    public function __construct(
        private CompanySealUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $decoded = JsonRequestBodyParser::parse($request);

        $imageBase64 = SealImageValidator::validate($decoded['image_base64'] ?? null);

        $this->useCase->save(AuthContext::userId($request), $imageBase64);

        return $this->json->create(['has_seal' => true]);
    }
}
