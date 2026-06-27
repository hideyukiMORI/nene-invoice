<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/recurring-invoices/{id}` — returns a recurring schedule and its
 * line template in the resolved organization (scoped by the repository via the
 * org holder).
 */
final readonly class GetRecurringInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private GetRecurringInvoiceByIdUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $result = $this->useCase->execute($id);

        return $this->json->create(RecurringInvoiceResponse::toArray($result->schedule, $result->lines));
    }
}
