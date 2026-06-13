<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Routing\Router;
use NeneInvoice\Payment\Gateway\PaymentGatewayException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /pay/{token}/charge` — public, no authentication. Receives the single-use
 * card token posted by PAY.JP Checkout (`payjp-token`) and charges the invoice's
 * outstanding balance. Renders a minimal HTML result page (browser form submit).
 *
 * Not-payable links render 404; a declined/failed charge renders 402. No card
 * data is read or stored (SAQ-A).
 */
final readonly class ChargePaymentLinkHandler implements RequestHandlerInterface
{
    public function __construct(
        private ChargePaymentLinkUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params   = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $rawToken = is_array($params) && isset($params['token']) ? (string) $params['token'] : '';

        $body      = $request->getParsedBody();
        $cardToken = is_array($body) && isset($body['payjp-token']) ? (string) $body['payjp-token'] : '';

        if ($cardToken === '') {
            return $this->html(400, $this->messagePage('お支払いに失敗しました', 'カード情報が送信されませんでした。'));
        }

        try {
            $result = $this->useCase->execute($rawToken, $cardToken);
        } catch (PaymentLinkNotPayableException) {
            return $this->html(404, $this->messagePage('お支払いリンクが無効です', 'このリンクは支払えません。'));
        } catch (PaymentGatewayException) {
            return $this->html(402, $this->messagePage('お支払いに失敗しました', 'カードが拒否されたか、決済に失敗しました。'));
        }

        $yen = htmlspecialchars(number_format($result->amountCents), ENT_QUOTES, 'UTF-8');

        return $this->html(200, $this->messagePage('お支払いが完了しました', "¥{$yen} のお支払いを受け付けました。"));
    }

    private function messagePage(string $title, string $detail): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!doctype html>
            <html lang="ja">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{$t}</title></head>
            <body><main><h1>{$t}</h1><p>{$d}</p></main></body>
            </html>
            HTML;
    }

    private function html(int $status, string $body): ResponseInterface
    {
        return $this->psr17->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->psr17->createStream($body));
    }
}
