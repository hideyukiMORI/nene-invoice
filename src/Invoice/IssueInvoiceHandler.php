<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/invoices/{id}/issue` — issues a draft invoice (assigns a number,
 * validates qualified-invoice requirements, locks it).
 */
final readonly class IssueInvoiceHandler implements RequestHandlerInterface
{
    public function __construct(
        private IssueInvoiceUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $decoded = json_decode((string) $request->getBody(), true);
        $decoded = is_array($decoded) ? $decoded : [];

        $qualified = !array_key_exists('qualified', $decoded) || $decoded['qualified'] === true;
        $dueAtValue = $decoded['due_at'] ?? null;
        $dueAt = is_string($dueAtValue) && $dueAtValue !== '' ? $dueAtValue : null;

        $result = $this->useCase->execute(AuthContext::userId($request), $id, new IssueInvoiceInput($qualified, $dueAt));

        return $this->json->create(InvoiceResponse::toArray($result->invoice, $result->lines));
    }
}
