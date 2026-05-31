<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\Invoice\Pdf\InvoicePdfGenerator;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Mailer\MailerInterface;
use NeneInvoice\Payment\PaymentRepositoryInterface;
use NeneInvoice\Quote\QuoteRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the Invoice domain: repository, use cases (convert / list / get),
 * handlers, the not-found exception handler, and the route registrar.
 */
final readonly class InvoiceServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                InvoiceRepositoryInterface::class,
                static function (ContainerInterface $c): InvoiceRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoInvoiceRepository($query, self::orgHolder($c));
                },
            )
            ->set(
                ConvertQuoteToInvoiceUseCase::class,
                static fn (ContainerInterface $c): ConvertQuoteToInvoiceUseCase => new ConvertQuoteToInvoiceUseCase(
                    self::resolve($c, QuoteRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, AuditRecorderInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                CreateInvoiceUseCase::class,
                static fn (ContainerInterface $c): CreateInvoiceUseCase => new CreateInvoiceUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, ClientRepositoryInterface::class),
                    self::resolve($c, TaxCalculator::class),
                    self::resolve($c, AuditRecorderInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                IssueInvoiceUseCase::class,
                static fn (ContainerInterface $c): IssueInvoiceUseCase => new IssueInvoiceUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, CompanySettingsRepositoryInterface::class),
                    self::resolve($c, DocumentNumberGenerator::class),
                    self::resolve($c, AuditRecorderInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                ListInvoicesUseCase::class,
                static fn (ContainerInterface $c): ListInvoicesUseCase => new ListInvoicesUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                ),
            )
            ->set(
                GetInvoiceByIdUseCase::class,
                static fn (ContainerInterface $c): GetInvoiceByIdUseCase => new GetInvoiceByIdUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                ),
            )
            ->set(
                ConvertQuoteToInvoiceHandler::class,
                static fn (ContainerInterface $c): ConvertQuoteToInvoiceHandler => new ConvertQuoteToInvoiceHandler(
                    self::resolve($c, ConvertQuoteToInvoiceUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                ListInvoicesHandler::class,
                static fn (ContainerInterface $c): ListInvoicesHandler => new ListInvoicesHandler(
                    self::resolve($c, ListInvoicesUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                GetInvoiceByIdHandler::class,
                static fn (ContainerInterface $c): GetInvoiceByIdHandler => new GetInvoiceByIdHandler(
                    self::resolve($c, GetInvoiceByIdUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                CreateInvoiceHandler::class,
                static fn (ContainerInterface $c): CreateInvoiceHandler => new CreateInvoiceHandler(
                    self::resolve($c, CreateInvoiceUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                IssueInvoiceHandler::class,
                static fn (ContainerInterface $c): IssueInvoiceHandler => new IssueInvoiceHandler(
                    self::resolve($c, IssueInvoiceUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                InvoiceNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): InvoiceNotFoundExceptionHandler => new InvoiceNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                InvoiceValidationExceptionHandler::class,
                static fn (ContainerInterface $c): InvoiceValidationExceptionHandler => new InvoiceValidationExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                QualifiedInvoiceIncompleteExceptionHandler::class,
                static fn (ContainerInterface $c): QualifiedInvoiceIncompleteExceptionHandler => new QualifiedInvoiceIncompleteExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                InvoicePdfGenerator::class,
                static fn (ContainerInterface $c): InvoicePdfGenerator => new InvoicePdfGenerator(
                    self::resolve($c, TaxCalculator::class),
                ),
            )
            ->set(
                GenerateInvoicePdfUseCase::class,
                static fn (ContainerInterface $c): GenerateInvoicePdfUseCase => new GenerateInvoicePdfUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, PaymentRepositoryInterface::class),
                    self::resolve($c, CompanySettingsRepositoryInterface::class),
                    self::resolve($c, ClientRepositoryInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                GetInvoicePdfHandler::class,
                static fn (ContainerInterface $c): GetInvoicePdfHandler => new GetInvoicePdfHandler(
                    self::resolve($c, GenerateInvoicePdfUseCase::class),
                    self::resolve($c, InvoicePdfGenerator::class),
                    self::resolve($c, Psr17Factory::class),
                ),
            )
            ->set(
                SendInvoiceEmailUseCase::class,
                static fn (ContainerInterface $c): SendInvoiceEmailUseCase => new SendInvoiceEmailUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, ClientRepositoryInterface::class),
                    self::resolve($c, CompanySettingsRepositoryInterface::class),
                    self::resolve($c, InvoicePdfGenerator::class),
                    self::resolve($c, MailerInterface::class),
                    self::orgHolder($c),
                    (string) (getenv('MAIL_FROM_NAME') ?: 'NeNe Invoice'),
                ),
            )
            ->set(
                SendInvoiceEmailHandler::class,
                static fn (ContainerInterface $c): SendInvoiceEmailHandler => new SendInvoiceEmailHandler(
                    self::resolve($c, SendInvoiceEmailUseCase::class),
                    self::resolve($c, Psr17Factory::class),
                ),
            )
            ->set(
                InvoiceEmailExceptionHandler::class,
                static fn (ContainerInterface $c): InvoiceEmailExceptionHandler => new InvoiceEmailExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                InvoiceRouteRegistrar::class,
                static function (ContainerInterface $c): InvoiceRouteRegistrar {
                    $list      = $c->get(ListInvoicesHandler::class);
                    $get       = $c->get(GetInvoiceByIdHandler::class);
                    $create    = $c->get(CreateInvoiceHandler::class);
                    $convert   = $c->get(ConvertQuoteToInvoiceHandler::class);
                    $issue     = $c->get(IssueInvoiceHandler::class);
                    $pdf       = $c->get(GetInvoicePdfHandler::class);
                    $sendEmail = $c->get(SendInvoiceEmailHandler::class);

                    if (!$list instanceof ListInvoicesHandler || !$get instanceof GetInvoiceByIdHandler || !$create instanceof CreateInvoiceHandler || !$convert instanceof ConvertQuoteToInvoiceHandler || !$issue instanceof IssueInvoiceHandler || !$pdf instanceof GetInvoicePdfHandler || !$sendEmail instanceof SendInvoiceEmailHandler) {
                        throw new LogicException('Invoice handler services are invalid.');
                    }

                    return new InvoiceRouteRegistrar($list, $get, $create, $convert, $issue, $pdf, $sendEmail);
                },
            );
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private static function resolve(ContainerInterface $c, string $id): object
    {
        $service = $c->get($id);

        if (!$service instanceof $id) {
            throw new LogicException(sprintf('Service %s is invalid.', $id));
        }

        return $service;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        return self::resolve($c, JsonResponseFactory::class);
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        return self::resolve($c, ProblemDetailsResponseFactory::class);
    }

    /** @return RequestScopedHolder<int> */
    private static function orgHolder(ContainerInterface $c): RequestScopedHolder
    {
        $holder = $c->get(ApplicationServiceProvider::ORG_ID_HOLDER);

        if (!$holder instanceof RequestScopedHolder) {
            throw new LogicException('Org id holder service is invalid.');
        }

        return $holder;
    }
}
