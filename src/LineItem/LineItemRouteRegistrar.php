<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LineItemRouteRegistrar
{
    public function __construct(private ListLineItemSuggestionsHandler $suggestions)
    {
    }

    public function __invoke(Router $router): void
    {
        $suggestions = $this->suggestions;
        $router->get(
            '/admin/line-items/suggestions',
            static fn (ServerRequestInterface $r) => $suggestions->handle($r),
        );
    }
}
