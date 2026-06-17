<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Nene2\Routing\Router;

/**
 * Registers authentication routes. Invoked with the {@see Router} during runtime
 * assembly (see {@see \NeneInvoice\ApplicationServiceProvider}).
 */
final readonly class AuthRouteRegistrar
{
    public function __construct(
        private LoginHandler $loginHandler,
        private GetCurrentUserHandler $getCurrentUserHandler,
        private RefreshHandler $refreshHandler,
        private LogoutHandler $logoutHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $router->post('/auth/login', fn ($request) => $this->loginHandler->handle($request));
        // Public (cookie-authenticated): silent re-auth and server-side logout — ADR 0014.
        $router->post('/auth/refresh', fn ($request) => $this->refreshHandler->handle($request));
        $router->post('/auth/logout', fn ($request) => $this->logoutHandler->handle($request));
        $router->get('/admin/me', fn ($request) => $this->getCurrentUserHandler->handle($request));
    }
}
