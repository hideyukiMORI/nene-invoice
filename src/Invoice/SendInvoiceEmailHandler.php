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
 * `POST /admin/invoices/{id}/send-email` — sends the invoice PDF to the client.
 *
 * Non-demo organizations send for real and get `204 No Content` on success.
 *
 * Demo organizations (identified by the `demo.slugPrefix` on the resolved org
 * slug, set by OrgResolverMiddleware as `nene2.org.slug`) must NOT send: their
 * seeded clients use fictitious `.example` addresses that always bounce with a
 * 502, which reads like a product failure in a sales demo (#626). Instead the
 * handler returns `200` with a JSON preview `{ preview, recipient, subject,
 * body_html }` so the UI can show what *would* be sent, clearly labelled as a
 * demo that did not actually deliver.
 *
 * MailerException and InvoiceEmailException bubble up to domain exception
 * handlers.
 */
final readonly class SendInvoiceEmailHandler implements RequestHandlerInterface
{
    /**
     * @param string $demoSlugPrefix organization-slug prefix that marks a demo
     *                                org; empty disables demo detection so every
     *                                org sends for real (safe default)
     */
    public function __construct(
        private SendInvoiceEmailUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private string $demoSlugPrefix,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id     = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        if ($this->isDemoOrg($request)) {
            $preview = $this->useCase->preview($id);

            return $this->json->create([
                'preview'   => true,
                'recipient' => $preview->recipient,
                'subject'   => $preview->subject,
                'body_html' => $preview->bodyHtml,
            ]);
        }

        $this->useCase->execute(AuthContext::userId($request), $id);

        return $this->json->createEmpty(204);
    }

    private function isDemoOrg(ServerRequestInterface $request): bool
    {
        if ($this->demoSlugPrefix === '') {
            return false;
        }

        $slug = $request->getAttribute('nene2.org.slug');

        return is_string($slug) && str_starts_with($slug, $this->demoSlugPrefix);
    }
}
