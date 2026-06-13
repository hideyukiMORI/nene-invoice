<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/company-settings/seal` — returns the organization's seal (社印) as
 * a base64 PNG for preview, or `has_seal: false` when none is set. Auth- and
 * organization-scoped (manage_company_settings — CapabilityResolver).
 */
final readonly class GetCompanySealHandler implements RequestHandlerInterface
{
    public function __construct(
        private CompanySealUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $imageBase64 = $this->useCase->get();

        return $this->json->create([
            'has_seal'     => $imageBase64 !== null,
            'image_base64' => $imageBase64,
        ]);
    }
}
