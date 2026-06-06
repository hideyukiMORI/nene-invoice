<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Routing\Router;
use NeneInvoice\Auth\AuthContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /admin/invoices/{id}/send-email` — sends the invoice PDF to the client.
 * Returns 204 No Content on success.
 * MailerException and InvoiceEmailException bubble up to domain exception handlers.
 */
final readonly class SendInvoiceEmailHandler implements RequestHandlerInterface
{
    public function __construct(
        private SendInvoiceEmailUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $this->useCase->execute(AuthContext::userId($request), $id);

        return $this->psr17->createResponse(204);
    }
}
