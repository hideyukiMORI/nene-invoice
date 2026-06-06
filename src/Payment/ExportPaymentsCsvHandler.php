<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/payments/export` — exports all valid payments as a CSV file.
 * Requires ViewBilling capability (resolved by the `/admin/payments` prefix).
 */
final readonly class ExportPaymentsCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ExportPaymentsCsvUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csv      = $this->useCase->execute();
        $filename = 'payments-' . date('Y-m-d') . '.csv';
        $stream   = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
