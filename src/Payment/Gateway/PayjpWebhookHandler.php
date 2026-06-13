<?php

declare(strict_types=1);

namespace NeneInvoice\Payment\Gateway;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\PaymentLink\RecordSettlementUseCaseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /webhooks/payjp` — public, no session. Authenticates the request by the
 * shared-secret header `X-Payjp-Webhook-Token` (PAY.JP does **not** HMAC-sign
 * webhooks — verified against docs.pay.jp/v1/webhook), then records a confirmed
 * `charge.succeeded` settlement against its payment link.
 *
 * Returns 401 for a missing/incorrect token (fail-closed when unconfigured),
 * 400 for a malformed body, and **200** for everything else — including ignored
 * event types and unresolvable charges — so PAY.JP (which retries up to 3× on
 * non-2xx) stops retrying a permanently-handled event. No card data is read.
 */
final readonly class PayjpWebhookHandler implements RequestHandlerInterface
{
    private const SETTLEMENT_EVENT = 'charge.succeeded';

    public function __construct(
        private RecordSettlementUseCaseInterface $useCase,
        private JsonResponseFactory $json,
        private ProblemDetailsResponseFactory $problemDetails,
        private string $expectedToken,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Fail closed: an unconfigured token rejects all webhooks.
        $presented = $request->getHeaderLine('X-Payjp-Webhook-Token');
        if ($this->expectedToken === '' || !hash_equals($this->expectedToken, $presented)) {
            return $this->problemDetails->create($request, 'invalid-webhook-token', 'Unauthorized', 401, 'Webhook token is missing or incorrect.');
        }

        $decoded = json_decode((string) $request->getBody(), true);
        if (!is_array($decoded)) {
            return $this->problemDetails->create($request, 'invalid-json', 'Bad Request', 400, 'Webhook body is not a JSON object.');
        }

        $type = is_string($decoded['type'] ?? null) ? $decoded['type'] : '';
        if ($type !== self::SETTLEMENT_EVENT) {
            // Acknowledge unhandled event types without retrying.
            return $this->json->create(['status' => 'ignored'], 200);
        }

        $charge = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $chargeId = is_string($charge['id'] ?? null) ? $charge['id'] : '';
        if ($chargeId === '') {
            return $this->json->create(['status' => 'ignored'], 200);
        }

        $amountCents   = (int) ($charge['amount'] ?? 0);
        $metadata      = is_array($charge['metadata'] ?? null) ? $charge['metadata'] : [];
        $rawLinkId     = $metadata['payment_link_id'] ?? null;
        $paymentLinkId = is_numeric($rawLinkId) ? (int) $rawLinkId : null;

        $outcome = $this->useCase->execute($paymentLinkId, $chargeId, $amountCents);

        return $this->json->create(['status' => strtolower($outcome->name)], 200);
    }
}
