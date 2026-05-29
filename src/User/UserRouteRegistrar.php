<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers user management routes. All require the `manage_users` capability
 * (admin), enforced by CapabilityMiddleware, and are scoped to the caller's
 * organization.
 */
final readonly class UserRouteRegistrar
{
    public function __construct(
        private ListUsersHandler $listHandler,
        private GetUserByIdHandler $getHandler,
        private CreateUserHandler $createHandler,
        private UpdateUserHandler $updateHandler,
        private DeleteUserHandler $deleteHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $create = $this->createHandler;
        $update = $this->updateHandler;
        $delete = $this->deleteHandler;

        $router->get('/admin/users', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/users', static fn (ServerRequestInterface $r) => $create->handle($r));
        $router->get('/admin/users/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->patch('/admin/users/{id}', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->delete('/admin/users/{id}', static fn (ServerRequestInterface $r) => $delete->handle($r));
    }
}
