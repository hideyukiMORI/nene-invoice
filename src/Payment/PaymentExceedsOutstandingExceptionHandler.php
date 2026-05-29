<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class PaymentExceedsOutstandingExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof PaymentExceedsOutstandingException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $outstanding = $exception instanceof PaymentExceedsOutstandingException
            ? $exception->outstandingCents
            : 0;

        return $this->problemDetails->create(
            $request,
            'payment-exceeds-outstanding',
            'Payment Exceeds Outstanding',
            422,
            $exception->getMessage(),
            ['outstanding_cents' => $outstanding],
        );
    }
}
