<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Payment\Gateway;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Payment\Gateway\PayjpWebhookHandler;
use NeneInvoice\PaymentLink\RecordSettlementUseCaseInterface;
use NeneInvoice\PaymentLink\SettlementOutcome;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class PayjpWebhookHandlerTest extends TestCase
{
    private const TOKEN = 'whook_test_secret';

    private Psr17Factory $psr17;

    /** @var array{paymentLinkId: ?int, chargeId: string, amountCents: int}|null */
    private ?array $captured = null;

    private RecordSettlementUseCaseInterface $useCase;

    protected function setUp(): void
    {
        $this->psr17    = new Psr17Factory();
        $this->captured = null;

        $test = $this;
        $this->useCase = new class ($test) implements RecordSettlementUseCaseInterface {
            public function __construct(private readonly PayjpWebhookHandlerTest $test)
            {
            }

            public function execute(?int $paymentLinkId, string $chargeId, int $amountCents): SettlementOutcome
            {
                $this->test->capture($paymentLinkId, $chargeId, $amountCents);

                return SettlementOutcome::Recorded;
            }
        };
    }

    public function capture(?int $paymentLinkId, string $chargeId, int $amountCents): void
    {
        $this->captured = ['paymentLinkId' => $paymentLinkId, 'chargeId' => $chargeId, 'amountCents' => $amountCents];
    }

    private function handler(string $expectedToken = self::TOKEN): PayjpWebhookHandler
    {
        return new PayjpWebhookHandler(
            $this->useCase,
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17),
            $expectedToken,
        );
    }

    private function request(string $body, ?string $token = self::TOKEN): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('POST', '/webhooks/payjp');
        if ($token !== null) {
            $request = $request->withHeader('X-Payjp-Webhook-Token', $token);
        }

        return $request->withBody($this->psr17->createStream($body));
    }

    private function chargeEvent(string $chargeId = 'ch_evt', int $amount = 1000, ?string $linkId = '7'): string
    {
        $metadata = $linkId !== null ? ['payment_link_id' => $linkId] : [];

        return (string) json_encode([
            'id'   => 'evnt_1',
            'type' => 'charge.succeeded',
            'data' => ['id' => $chargeId, 'amount' => $amount, 'metadata' => $metadata],
        ]);
    }

    public function test_rejects_missing_token(): void
    {
        $response = $this->handler()->handle($this->request($this->chargeEvent(), token: null));

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($this->captured);
    }

    public function test_rejects_incorrect_token(): void
    {
        $response = $this->handler()->handle($this->request($this->chargeEvent(), token: 'whook_wrong'));

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($this->captured);
    }

    public function test_fails_closed_when_token_unconfigured(): void
    {
        $response = $this->handler(expectedToken: '')->handle($this->request($this->chargeEvent()));

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($this->captured);
    }

    public function test_malformed_body_returns_400(): void
    {
        $response = $this->handler()->handle($this->request('not-json'));

        self::assertSame(400, $response->getStatusCode());
        self::assertNull($this->captured);
    }

    public function test_non_settlement_event_is_acknowledged_without_dispatch(): void
    {
        $body     = (string) json_encode(['id' => 'evnt_2', 'type' => 'charge.failed', 'data' => ['id' => 'ch_x']]);
        $response = $this->handler()->handle($this->request($body));

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->captured);
    }

    public function test_charge_succeeded_dispatches_parsed_values(): void
    {
        $response = $this->handler()->handle($this->request($this->chargeEvent('ch_evt', 1500, '7')));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($this->captured);
        self::assertSame(7, $this->captured['paymentLinkId']);
        self::assertSame('ch_evt', $this->captured['chargeId']);
        self::assertSame(1500, $this->captured['amountCents']);
    }

    public function test_charge_without_metadata_link_id_dispatches_null(): void
    {
        $response = $this->handler()->handle($this->request($this->chargeEvent('ch_evt', 1000, null)));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($this->captured);
        self::assertNull($this->captured['paymentLinkId']);
        self::assertSame('ch_evt', $this->captured['chargeId']);
    }
}
