<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Content negotiation for the public demo start route (#612).
 *
 * `GET /demo/{template}` is the one route real people open in a browser (the
 * tax-accountant referral link), so its errors must not surface as raw RFC 9457
 * JSON — a non-technical visitor reads that as "the site is broken". When the
 * client prefers `text/html`, the Problem Details error is replaced with a
 * small self-contained Japanese explanation page; API-shaped clients (no
 * `text/html` in Accept) keep the JSON untouched, as does the success redirect.
 *
 * The page never echoes request input (e.g. the template segment) — all copy is
 * fixed text plus numbers computed server-side (`Retry-After` minutes).
 */
final readonly class DemoBrowserErrorPage
{
    public function __construct(
        private Psr17Factory $responseFactory,
        private int $throttleLimit,
        private int $throttleWindowSeconds,
    ) {
    }

    public function apply(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($response->getStatusCode() < 400) {
            return $response;
        }

        if (!str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            return $response;
        }

        [$title, $message] = $this->copyFor($response);

        $html = $this->render($title, $message);
        $page = $this->responseFactory->createResponse($response->getStatusCode())
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        if ($response->hasHeader('Retry-After')) {
            $page = $page->withHeader('Retry-After', $response->getHeaderLine('Retry-After'));
        }

        $page->getBody()->write($html);

        return $page;
    }

    /** @return array{0: string, 1: string} [title, message] */
    private function copyFor(ResponseInterface $response): array
    {
        $windowHours = intdiv($this->throttleWindowSeconds, 3600);
        $windowLabel = $windowHours >= 1 ? "{$windowHours}時間" : intdiv($this->throttleWindowSeconds, 60) . '分';

        return match ($response->getStatusCode()) {
            429 => [
                'デモのご利用が集中しています',
                "同一ネットワーク（IP）からのデモ開始は{$windowLabel}に{$this->throttleLimit}回までに制限しています。"
                    . $this->retryAdvice($response),
            ],
            503 => [
                'ただいまデモが満席です',
                'お試し用のデモ環境が上限に達しています。古いデモは毎時自動的に整理されますので、しばらくしてからもう一度このリンクを開いてください。',
            ],
            404 => [
                'このデモは現在ご利用いただけません',
                'リンクの綴りが変わったか、この環境ではデモが無効になっています。お手数ですが、案内元のリンクをもう一度ご確認ください。',
            ],
            default => [
                'デモを開始できませんでした',
                '一時的な問題が発生しました。しばらくしてからもう一度このリンクを開いてください。',
            ],
        };
    }

    private function retryAdvice(ResponseInterface $response): string
    {
        $retryAfter = (int) $response->getHeaderLine('Retry-After');

        if ($retryAfter <= 0) {
            return 'しばらくしてからもう一度このリンクを開いてください。';
        }

        $minutes = max(1, (int) ceil($retryAfter / 60));

        return "約{$minutes}分後にもう一度このリンクを開いてください。";
    }

    private function render(string $title, string $message): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ja">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>{$safeTitle} — NeNe Invoice デモ</title>
            <style>
            body { margin: 0; font-family: system-ui, -apple-system, "Hiragino Sans", "Noto Sans JP", sans-serif;
                   background: #f5f6f8; color: #1f2933; display: grid; min-height: 100vh; place-items: center; }
            main { background: #fff; border: 1px solid #e0e4e8; border-radius: 12px; padding: 2.5rem 2rem;
                   max-width: 30rem; margin: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
            h1 { font-size: 1.2rem; margin: 0 0 .75rem; }
            p { margin: 0; line-height: 1.8; }
            .brand { margin-top: 1.5rem; font-size: .8rem; color: #6b7684; }
            </style>
            </head>
            <body>
            <main>
            <h1>{$safeTitle}</h1>
            <p>{$safeMessage}</p>
            <p class="brand">NeNe Invoice — お試しデモ</p>
            </main>
            </body>
            </html>
            HTML;
    }
}
