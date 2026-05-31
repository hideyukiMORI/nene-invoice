<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Mailer\MailerInterface;
use NeneInvoice\Mailer\MailMessage;

/** In-process mailer that records sent messages instead of sending them over SMTP. */
final class RecordingMailer implements MailerInterface
{
    /** @var list<MailMessage> */
    public array $sent = [];

    public function send(MailMessage $message): void
    {
        $this->sent[] = $message;
    }
}
