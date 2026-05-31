<?php

declare(strict_types=1);

namespace NeneInvoice\Mailer;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP mailer backed by PHPMailer. Configuration is provided at construction
 * time from environment variables (MAIL_HOST, MAIL_PORT, MAIL_FROM_ADDRESS,
 * MAIL_FROM_NAME, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION).
 */
final readonly class SmtpMailer implements MailerInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private string $fromAddress,
        private string $fromName,
        private string $username,
        private string $password,
        private string $encryption,
    ) {
    }

    public function send(MailMessage $message): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->Port       = $this->port;
            $mail->SMTPAuth   = $this->username !== '';
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->SMTPSecure = $this->encryption !== '' ? $this->encryption : PHPMailer::ENCRYPTION_SMTPS;

            if ($this->encryption === '') {
                $mail->SMTPSecure = '';
                $mail->SMTPAuth   = false;
            }

            $mail->CharSet  = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($message->toAddress, $message->toName);

            $mail->isHTML(true);
            $mail->Subject = $message->subject;
            $mail->Body    = $message->bodyHtml;
            $mail->AltBody = strip_tags($message->bodyHtml);

            if (
                $message->attachmentBytes !== null
                && $message->attachmentName !== null
            ) {
                $mail->addStringAttachment(
                    $message->attachmentBytes,
                    $message->attachmentName,
                    PHPMailer::ENCODING_BASE64,
                    $message->attachmentMime ?? 'application/octet-stream',
                );
            }

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new MailerException('SMTP send failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
