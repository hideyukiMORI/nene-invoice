<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditServiceProvider;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;
use NeneInvoice\Company\Seal\CompanySealRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\PdoLineItemRepository;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\Pdf\MpdfFactory;
use NeneInvoice\Quote\Pdf\QuotePdfGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the Quote domain: repository, use cases, handlers, domain exception
 * handlers, and the route registrar.
 */
final readonly class QuoteServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                QuoteRepositoryInterface::class,
                static function (ContainerInterface $c): QuoteRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoQuoteRepository($query, self::orgHolder($c));
                },
            )
            ->set(TaxCalculator::class, static fn (ContainerInterface $c): TaxCalculator => new TaxCalculator())
            ->set(
                CreateQuoteUseCaseInterface::class,
                static function (ContainerInterface $c): CreateQuoteUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new CreateQuoteUseCase(
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): QuoteRepositoryInterface => new PdoQuoteRepository($exec, $orgHolder),
                        static fn (DatabaseQueryExecutorInterface $exec): LineItemRepositoryInterface => new PdoLineItemRepository($exec, $orgHolder),
                        self::resolve($c, ClientRepositoryInterface::class),
                        self::resolve($c, CompanySettingsRepositoryInterface::class),
                        self::resolve($c, DocumentNumberGenerator::class),
                        self::resolve($c, TaxCalculator::class),
                        AuditServiceProvider::recorderFactory($c),
                        self::resolve($c, ClockInterface::class),
                        $orgHolder,
                    );
                },
            )
            ->set(
                ChangeQuoteStatusUseCaseInterface::class,
                static function (ContainerInterface $c): ChangeQuoteStatusUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new ChangeQuoteStatusUseCase(
                        self::quotes($c),
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): QuoteRepositoryInterface => new PdoQuoteRepository($exec, $orgHolder),
                        AuditServiceProvider::recorderFactory($c),
                        self::resolve($c, ClockInterface::class),
                        $orgHolder,
                    );
                },
            )
            ->set(ListQuotesUseCaseInterface::class, static fn (ContainerInterface $c): ListQuotesUseCase => new ListQuotesUseCase(self::quotes($c)))
            ->set(
                ExportQuotesCsvUseCaseInterface::class,
                static fn (ContainerInterface $c): ExportQuotesCsvUseCase => new ExportQuotesCsvUseCase(self::quotes($c)),
            )
            ->set(
                GetQuoteByIdUseCaseInterface::class,
                static fn (ContainerInterface $c): GetQuoteByIdUseCase => new GetQuoteByIdUseCase(
                    self::quotes($c),
                    self::resolve($c, LineItemRepositoryInterface::class),
                ),
            )
            ->set(
                CreateQuoteHandler::class,
                static fn (ContainerInterface $c): CreateQuoteHandler => new CreateQuoteHandler(
                    self::resolve($c, CreateQuoteUseCaseInterface::class),
                    self::json($c),
                ),
            )
            ->set(
                ListQuotesHandler::class,
                static fn (ContainerInterface $c): ListQuotesHandler => new ListQuotesHandler(
                    self::resolve($c, ListQuotesUseCaseInterface::class),
                    self::json($c),
                ),
            )
            ->set(
                GetQuoteByIdHandler::class,
                static fn (ContainerInterface $c): GetQuoteByIdHandler => new GetQuoteByIdHandler(
                    self::resolve($c, GetQuoteByIdUseCaseInterface::class),
                    self::json($c),
                ),
            )
            ->set(
                ChangeQuoteStatusHandler::class,
                static fn (ContainerInterface $c): ChangeQuoteStatusHandler => new ChangeQuoteStatusHandler(
                    self::resolve($c, ChangeQuoteStatusUseCaseInterface::class),
                    self::json($c),
                ),
            )
            ->set(
                GenerateQuotePdfUseCaseInterface::class,
                static fn (ContainerInterface $c): GenerateQuotePdfUseCase => new GenerateQuotePdfUseCase(
                    self::quotes($c),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, CompanySettingsRepositoryInterface::class),
                    self::resolve($c, ClientRepositoryInterface::class),
                    self::resolve($c, CompanySealRepositoryInterface::class),
                    self::orgHolder($c),
                ),
            )
            ->set(
                QuotePdfGenerator::class,
                static fn (ContainerInterface $c): QuotePdfGenerator => new QuotePdfGenerator(
                    self::resolve($c, TaxCalculator::class),
                    new MpdfFactory(),
                ),
            )
            ->set(
                GetQuotePdfHandler::class,
                static fn (ContainerInterface $c): GetQuotePdfHandler => new GetQuotePdfHandler(
                    self::resolve($c, GenerateQuotePdfUseCaseInterface::class),
                    self::resolve($c, QuotePdfGenerator::class),
                    self::resolve($c, Psr17Factory::class),
                ),
            )
            ->set(
                ExportQuotesCsvHandler::class,
                static fn (ContainerInterface $c): ExportQuotesCsvHandler => new ExportQuotesCsvHandler(
                    self::resolve($c, ExportQuotesCsvUseCaseInterface::class),
                    self::resolve($c, Psr17Factory::class),
                ),
            )
            ->set(
                QuoteNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): QuoteNotFoundExceptionHandler => new QuoteNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                QuoteValidationExceptionHandler::class,
                static fn (ContainerInterface $c): QuoteValidationExceptionHandler => new QuoteValidationExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                InvalidStateTransitionExceptionHandler::class,
                static fn (ContainerInterface $c): InvalidStateTransitionExceptionHandler => new InvalidStateTransitionExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                QuoteRouteRegistrar::class,
                static function (ContainerInterface $c): QuoteRouteRegistrar {
                    $list         = $c->get(ListQuotesHandler::class);
                    $get          = $c->get(GetQuoteByIdHandler::class);
                    $create       = $c->get(CreateQuoteHandler::class);
                    $changeStatus = $c->get(ChangeQuoteStatusHandler::class);
                    $pdf          = $c->get(GetQuotePdfHandler::class);
                    $exportCsv    = $c->get(ExportQuotesCsvHandler::class);

                    if (!$list instanceof ListQuotesHandler || !$get instanceof GetQuoteByIdHandler || !$create instanceof CreateQuoteHandler || !$changeStatus instanceof ChangeQuoteStatusHandler || !$pdf instanceof GetQuotePdfHandler || !$exportCsv instanceof ExportQuotesCsvHandler) {
                        throw new LogicException('Quote handler services are invalid.');
                    }

                    return new QuoteRouteRegistrar($list, $get, $create, $changeStatus, $pdf, $exportCsv);
                },
            );
    }

    private static function quotes(ContainerInterface $c): QuoteRepositoryInterface
    {
        $repo = $c->get(QuoteRepositoryInterface::class);

        if (!$repo instanceof QuoteRepositoryInterface) {
            throw new LogicException('Quote repository service is invalid.');
        }

        return $repo;
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
