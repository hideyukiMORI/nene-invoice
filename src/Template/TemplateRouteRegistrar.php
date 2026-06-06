<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers template (雛形) routes. Reads require `view_billing`, mutations
 * require `manage_billing` (CapabilityMiddleware); all scoped to the org.
 */
final readonly class TemplateRouteRegistrar
{
    public function __construct(
        private ListTemplatesHandler $listHandler,
        private GetTemplateByIdHandler $getHandler,
        private CreateTemplateHandler $createHandler,
        private UpdateTemplateHandler $updateHandler,
        private DeleteTemplateHandler $deleteHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $create = $this->createHandler;
        $update = $this->updateHandler;
        $delete = $this->deleteHandler;

        $router->get('/admin/templates', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/templates', static fn (ServerRequestInterface $r) => $create->handle($r));
        $router->get('/admin/templates/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->patch('/admin/templates/{id}', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->delete('/admin/templates/{id}', static fn (ServerRequestInterface $r) => $delete->handle($r));
    }
}
