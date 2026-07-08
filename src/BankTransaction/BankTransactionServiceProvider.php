<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

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
use NeneInvoice\Payment\RecordPaymentUseCaseInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires the BankTransaction domain (#505): repositories, use cases (import,
 * suggest, confirm, ignore, list), handlers, domain exception handlers, and the
 * route registrar.
 */
final readonly class BankTransactionServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                BankTransactionRepositoryInterface::class,
                static fn (ContainerInterface $c): BankTransactionRepositoryInterface => new PdoBankTransactionRepository(self::query($c), self::orgHolder($c), self::resolve($c, ClockInterface::class)),
            )
            ->set(
                PayerAliasRepositoryInterface::class,
                static fn (ContainerInterface $c): PayerAliasRepositoryInterface => new PdoPayerAliasRepository(self::query($c), self::orgHolder($c), self::resolve($c, ClockInterface::class)),
            )
            ->set(
                MatchCandidateRepositoryInterface::class,
                static fn (ContainerInterface $c): MatchCandidateRepositoryInterface => new PdoMatchCandidateRepository(self::query($c), self::orgHolder($c)),
            )
            ->set(
                ImportBankTransactionsUseCase::class,
                static function (ContainerInterface $c): ImportBankTransactionsUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new ImportBankTransactionsUseCase(
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): BankTransactionRepositoryInterface => new PdoBankTransactionRepository($exec, $orgHolder, self::resolve($c, ClockInterface::class)),
                    );
                },
            )
            ->set(
                SuggestMatchesUseCase::class,
                static fn (ContainerInterface $c): SuggestMatchesUseCase => new SuggestMatchesUseCase(
                    self::resolve($c, BankTransactionRepositoryInterface::class),
                    self::resolve($c, MatchCandidateRepositoryInterface::class),
                    self::resolve($c, PayerAliasRepositoryInterface::class),
                ),
            )
            ->set(
                ConfirmMatchUseCase::class,
                static fn (ContainerInterface $c): ConfirmMatchUseCase => new ConfirmMatchUseCase(
                    self::resolve($c, BankTransactionRepositoryInterface::class),
                    self::resolve($c, PayerAliasRepositoryInterface::class),
                    self::resolve($c, RecordPaymentUseCaseInterface::class),
                ),
            )
            ->set(
                IgnoreBankTransactionUseCase::class,
                static fn (ContainerInterface $c): IgnoreBankTransactionUseCase => new IgnoreBankTransactionUseCase(
                    self::resolve($c, BankTransactionRepositoryInterface::class),
                ),
            )
            ->set(
                ListBankTransactionsUseCase::class,
                static fn (ContainerInterface $c): ListBankTransactionsUseCase => new ListBankTransactionsUseCase(
                    self::resolve($c, BankTransactionRepositoryInterface::class),
                ),
            )
            ->set(
                ImportBankTransactionsHandler::class,
                static fn (ContainerInterface $c): ImportBankTransactionsHandler => new ImportBankTransactionsHandler(
                    self::resolve($c, ImportBankTransactionsUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                ListBankTransactionsHandler::class,
                static fn (ContainerInterface $c): ListBankTransactionsHandler => new ListBankTransactionsHandler(
                    self::resolve($c, ListBankTransactionsUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                BankTransactionSuggestionsHandler::class,
                static fn (ContainerInterface $c): BankTransactionSuggestionsHandler => new BankTransactionSuggestionsHandler(
                    self::resolve($c, SuggestMatchesUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                ConfirmBankTransactionMatchHandler::class,
                static fn (ContainerInterface $c): ConfirmBankTransactionMatchHandler => new ConfirmBankTransactionMatchHandler(
                    self::resolve($c, ConfirmMatchUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                IgnoreBankTransactionHandler::class,
                static fn (ContainerInterface $c): IgnoreBankTransactionHandler => new IgnoreBankTransactionHandler(
                    self::resolve($c, IgnoreBankTransactionUseCase::class),
                    self::json($c),
                ),
            )
            ->set(
                BankTransactionNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): BankTransactionNotFoundExceptionHandler => new BankTransactionNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                BankTransactionValidationExceptionHandler::class,
                static fn (ContainerInterface $c): BankTransactionValidationExceptionHandler => new BankTransactionValidationExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                BankTransactionRouteRegistrar::class,
                static function (ContainerInterface $c): BankTransactionRouteRegistrar {
                    $import      = $c->get(ImportBankTransactionsHandler::class);
                    $list        = $c->get(ListBankTransactionsHandler::class);
                    $suggestions = $c->get(BankTransactionSuggestionsHandler::class);
                    $confirm     = $c->get(ConfirmBankTransactionMatchHandler::class);
                    $ignore      = $c->get(IgnoreBankTransactionHandler::class);

                    if (!$import instanceof ImportBankTransactionsHandler || !$list instanceof ListBankTransactionsHandler || !$suggestions instanceof BankTransactionSuggestionsHandler || !$confirm instanceof ConfirmBankTransactionMatchHandler || !$ignore instanceof IgnoreBankTransactionHandler) {
                        throw new LogicException('Bank transaction handler services are invalid.');
                    }

                    return new BankTransactionRouteRegistrar($import, $list, $suggestions, $confirm, $ignore);
                },
            );
    }

    private static function query(ContainerInterface $c): DatabaseQueryExecutorInterface
    {
        return self::resolve($c, DatabaseQueryExecutorInterface::class);
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
