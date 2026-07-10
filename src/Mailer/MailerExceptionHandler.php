<?php

declare(strict_types=1);

namespace NeneInvoice\Mailer;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Maps a mail-transport failure ({@see MailerException}) to a `502 Bad Gateway`
 * `email-delivery-failed` Problem Details response instead of a generic 500
 * (#621): the upstream SMTP hop failed, the request itself was valid.
 *
 * The transport error (which may name SMTP hosts) goes to `error_log` for ops;
 * the response detail stays generic so nothing about the mail infrastructure
 * leaks to API clients.
 */
final readonly class MailerExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(private ProblemDetailsResponseFactory $problemDetails)
    {
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof MailerException;
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        assert($exception instanceof MailerException);

        error_log('NeNe Invoice: email delivery failed: ' . $exception->getMessage());

        return $this->problemDetails->create(
            $request,
            'email-delivery-failed',
            'Email Delivery Failed',
            502,
            'The email could not be delivered because the mail transport failed. Check the MAIL_* configuration or try again later.',
        );
    }
}
