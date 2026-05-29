<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class ClientNotFoundExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof ClientNotFoundException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create($request, 'client-not-found', 'Not Found', 404, 'The requested client was not found.');
    }
}
