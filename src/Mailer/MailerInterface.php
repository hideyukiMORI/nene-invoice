<?php

declare(strict_types=1);

namespace NeneInvoice\Mailer;

/** Sends a single email. Implementations are responsible for transport details. */
interface MailerInterface
{
    /**
     * @throws MailerException
     */
    public function send(MailMessage $message): void;
}
