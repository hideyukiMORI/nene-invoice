<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Routing\Router;
use NeneInvoice\Company\Seal\DeleteCompanySealHandler;
use NeneInvoice\Company\Seal\GetCompanySealHandler;
use NeneInvoice\Company\Seal\PutCompanySealHandler;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the issuer-profile routes, including the company seal (社印) image
 * endpoints. All require `manage_company_settings` (admin), scoped to the
 * caller's organization.
 */
final readonly class CompanyRouteRegistrar
{
    public function __construct(
        private GetCompanySettingsHandler $getHandler,
        private UpdateCompanySettingsHandler $updateHandler,
        private GetCompanySealHandler $getSealHandler,
        private PutCompanySealHandler $putSealHandler,
        private DeleteCompanySealHandler $deleteSealHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $get = $this->getHandler;
        $update = $this->updateHandler;
        $getSeal = $this->getSealHandler;
        $putSeal = $this->putSealHandler;
        $deleteSeal = $this->deleteSealHandler;

        $router->get('/admin/company-settings', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->put('/admin/company-settings', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->get('/admin/company-settings/seal', static fn (ServerRequestInterface $r) => $getSeal->handle($r));
        $router->put('/admin/company-settings/seal', static fn (ServerRequestInterface $r) => $putSeal->handle($r));
        $router->delete('/admin/company-settings/seal', static fn (ServerRequestInterface $r) => $deleteSeal->handle($r));
    }
}
