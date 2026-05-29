<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\DocumentSequence\DocumentNumberGenerator;
use NeneInvoice\LineItem\LineItemRepositoryInterface;
use NeneInvoice\LineItem\TaxCalculator;
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

                    return new PdoQuoteRepository($query);
                },
            )
            ->set(TaxCalculator::class, static fn (ContainerInterface $c): TaxCalculator => new TaxCalculator())
            ->set(
                CreateQuoteUseCase::class,
                static fn (ContainerInterface $c): CreateQuoteUseCase => new CreateQuoteUseCase(
                    self::quotes($c),
                    self::resolve($c, LineItemRepositoryInterface::class),
                    self::resolve($c, ClientRepositoryInterface::class),
                    self::resolve($c, DocumentNumberGenerator::class),
                    self::resolve($c, TaxCalculator::class),
                    self::resolve($c, AuditRecorderInterface::class),
                ),
            )
            ->set(
                ChangeQuoteStatusUseCase::class,
                static fn (ContainerInterface $c): ChangeQuoteStatusUseCase => new ChangeQuoteStatusUseCase(
                    self::quotes($c),
                    self::resolve($c, AuditRecorderInterface::class),
                ),
            )
            ->set(ListQuotesUseCase::class, static fn (ContainerInterface $c): ListQuotesUseCase => new ListQuotesUseCase(self::quotes($c)))
            ->set(
                GetQuoteByIdUseCase::class,
                static fn (ContainerInterface $c): GetQuoteByIdUseCase => new GetQuoteByIdUseCase(
                    self::quotes($c),
                    self::resolve($c, LineItemRepositoryInterface::class),
                ),
            )
            ->set(
                CreateQuoteHandler::class,
                static fn (ContainerInterface $c): CreateQuoteHandler => new CreateQuoteHandler(
                    self::resolve($c, CreateQuoteUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                ListQuotesHandler::class,
                static fn (ContainerInterface $c): ListQuotesHandler => new ListQuotesHandler(
                    self::resolve($c, ListQuotesUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                GetQuoteByIdHandler::class,
                static fn (ContainerInterface $c): GetQuoteByIdHandler => new GetQuoteByIdHandler(
                    self::resolve($c, GetQuoteByIdUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
                ),
            )
            ->set(
                ChangeQuoteStatusHandler::class,
                static fn (ContainerInterface $c): ChangeQuoteStatusHandler => new ChangeQuoteStatusHandler(
                    self::resolve($c, ChangeQuoteStatusUseCase::class),
                    self::json($c),
                    self::problemDetails($c),
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
                    $list = $c->get(ListQuotesHandler::class);
                    $get = $c->get(GetQuoteByIdHandler::class);
                    $create = $c->get(CreateQuoteHandler::class);
                    $changeStatus = $c->get(ChangeQuoteStatusHandler::class);

                    if (!$list instanceof ListQuotesHandler || !$get instanceof GetQuoteByIdHandler || !$create instanceof CreateQuoteHandler || !$changeStatus instanceof ChangeQuoteStatusHandler) {
                        throw new LogicException('Quote handler services are invalid.');
                    }

                    return new QuoteRouteRegistrar($list, $get, $create, $changeStatus);
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
