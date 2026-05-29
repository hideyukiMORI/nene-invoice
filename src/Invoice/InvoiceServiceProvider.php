<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\Quote\QuoteRepositoryInterface;
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

                    return new PdoInvoiceRepository($query);
                },
            )
            ->set(
                ConvertQuoteToInvoiceUseCase::class,
                static fn (ContainerInterface $c): ConvertQuoteToInvoiceUseCase => new ConvertQuoteToInvoiceUseCase(
                    self::resolve($c, QuoteRepositoryInterface::class),
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, AuditRecorderInterface::class),
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
                ),
            )
            ->set(ListInvoicesUseCase::class, static fn (ContainerInterface $c): ListInvoicesUseCase => new ListInvoicesUseCase(self::resolve($c, InvoiceRepositoryInterface::class)))
            ->set(
                GetInvoiceByIdUseCase::class,
                static fn (ContainerInterface $c): GetInvoiceByIdUseCase => new GetInvoiceByIdUseCase(
                    self::resolve($c, InvoiceRepositoryInterface::class),
                    self::resolve($c, LineItemRepositoryInterface::class),
                ),
            )
            ->set(
                ConvertQuoteToInvoiceHandler::class,
                static fn (ContainerInterface $c): ConvertQuoteToInvoiceHandler => new ConvertQuoteToInvoiceHandler(
                    self::resolve($c, ConvertQuoteToInvoiceUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                ListInvoicesHandler::class,
                static fn (ContainerInterface $c): ListInvoicesHandler => new ListInvoicesHandler(
                    self::resolve($c, ListInvoicesUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                GetInvoiceByIdHandler::class,
                static fn (ContainerInterface $c): GetInvoiceByIdHandler => new GetInvoiceByIdHandler(
                    self::resolve($c, GetInvoiceByIdUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                IssueInvoiceHandler::class,
                static fn (ContainerInterface $c): IssueInvoiceHandler => new IssueInvoiceHandler(
                    self::resolve($c, IssueInvoiceUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
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
                InvoiceRouteRegistrar::class,
                static function (ContainerInterface $c): InvoiceRouteRegistrar {
                    $list = $c->get(ListInvoicesHandler::class);
                    $get = $c->get(GetInvoiceByIdHandler::class);
                    $convert = $c->get(ConvertQuoteToInvoiceHandler::class);
                    $issue = $c->get(IssueInvoiceHandler::class);

                    if (!$list instanceof ListInvoicesHandler || !$get instanceof GetInvoiceByIdHandler || !$convert instanceof ConvertQuoteToInvoiceHandler || !$issue instanceof IssueInvoiceHandler) {
                        throw new LogicException('Invoice handler services are invalid.');
                    }

                    return new InvoiceRouteRegistrar($list, $get, $convert, $issue);
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
}
