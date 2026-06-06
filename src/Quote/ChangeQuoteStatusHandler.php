<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneInvoice\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `PATCH /admin/quotes/{id}` — changes a quote's status (e.g. send, accept).
 */
final readonly class ChangeQuoteStatusHandler implements RequestHandlerInterface
{
    public function __construct(
        private ChangeQuoteStatusUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = is_array($params) && isset($params['id']) ? (int) $params['id'] : 0;

        $body = JsonRequestBodyParser::parse($request);
        $statusValue = $body['status'] ?? null;
        $target = is_string($statusValue) ? QuoteStatus::tryFrom($statusValue) : null;

        if ($target === null) {
            throw new ValidationException([new ValidationError('body.status', 'Status must be one of: draft, sent, accepted, rejected, expired.', 'invalid')]);
        }

        $quote = $this->useCase->execute(AuthContext::userId($request), $id, $target);

        return $this->json->create(QuoteResponse::toArray($quote));
    }
}
