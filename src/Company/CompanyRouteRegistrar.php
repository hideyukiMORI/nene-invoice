<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the issuer-profile routes. Both require `manage_company_settings`
 * (admin), scoped to the caller's organization.
 */
final readonly class CompanyRouteRegistrar
{
    public function __construct(
        private GetCompanySettingsHandler $getHandler,
        private UpdateCompanySettingsHandler $updateHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $get = $this->getHandler;
        $update = $this->updateHandler;

        $router->get('/admin/company-settings', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->put('/admin/company-settings', static fn (ServerRequestInterface $r) => $update->handle($r));
    }
}
