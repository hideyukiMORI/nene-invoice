<?php

declare(strict_types=1);

namespace NeneInvoice\GatewaySettings;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GatewaySettingsRouteRegistrar
{
    public function __construct(
        private GetGatewaySettingsHandler $getHandler,
        private TestGatewayConnectivityHandler $testHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $get  = $this->getHandler;
        $test = $this->testHandler;

        $router->get('/admin/gateway-settings', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->post('/admin/gateway-settings/test', static fn (ServerRequestInterface $r) => $test->handle($r));
    }
}
