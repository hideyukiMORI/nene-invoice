<?php

declare(strict_types=1);

namespace NeneInvoice\Mailer;

use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the SmtpMailer using MAIL_* environment variables loaded by ConfigLoader.
 *
 * Defaults to a Mailpit-compatible config (host=mailpit, port=1025, no auth)
 * which works with the docker-compose.yml in development.
 */
final readonly class MailerServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->set(
            MailerInterface::class,
            static function (ContainerInterface $c): MailerInterface {
                $host       = (string) (getenv('MAIL_HOST') ?: 'mailpit');
                $port       = (int) (getenv('MAIL_PORT') ?: '1025');
                $fromAddr   = (string) (getenv('MAIL_FROM_ADDRESS') ?: 'invoice@example.com');
                $fromName   = (string) (getenv('MAIL_FROM_NAME') ?: 'NeNe Invoice');
                $username   = (string) (getenv('MAIL_USERNAME') ?: '');
                $password   = (string) (getenv('MAIL_PASSWORD') ?: '');
                $encryption = (string) (getenv('MAIL_ENCRYPTION') ?: '');

                return new SmtpMailer($host, $port, $fromAddr, $fromName, $username, $password, $encryption);
            },
        );
    }
}
