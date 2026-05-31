<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class InvoiceEmailExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(private ProblemDetailsResponseFactory $problemDetails)
    {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof InvoiceEmailException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof InvoiceEmailException);

        return $this->problemDetails->create($request, $exception->slug, 'Unprocessable Entity', 422, $exception->getMessage());
    }
}
