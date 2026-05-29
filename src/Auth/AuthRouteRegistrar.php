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
    ) {
    }

    public function __invoke(Router $router): void
    {
        $router->post('/auth/login', fn ($request) => $this->loginHandler->handle($request));
    }
}
