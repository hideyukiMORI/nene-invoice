<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Closure;
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
use NeneInvoice\Company\Seal\CompanySealRepositoryInterface;
use NeneInvoice\Company\Seal\CompanySealUseCase;
use NeneInvoice\Company\Seal\CompanySealUseCaseInterface;
use NeneInvoice\Company\Seal\DeleteCompanySealHandler;
use NeneInvoice\Company\Seal\GetCompanySealHandler;
use NeneInvoice\Company\Seal\PdoCompanySealRepository;
use NeneInvoice\Company\Seal\PutCompanySealHandler;
use Psr\Container\ContainerInterface;

/**
 * Wires the Company (issuer profile) domain: repository, use cases, handlers,
 * domain exception handlers, and the route registrar.
 */
final readonly class CompanyServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                CompanySettingsRepositoryInterface::class,
                static function (ContainerInterface $c): CompanySettingsRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoCompanySettingsRepository($query, self::orgHolder($c), self::clock($c));
                },
            )
            ->set(GetCompanySettingsUseCaseInterface::class, static fn (ContainerInterface $c): GetCompanySettingsUseCase => new GetCompanySettingsUseCase(self::repository($c), self::orgHolder($c)))
            ->set(UpdateCompanySettingsUseCaseInterface::class, static fn (ContainerInterface $c): UpdateCompanySettingsUseCase => new UpdateCompanySettingsUseCase(self::repository($c), self::tx($c), self::repositoryFactory($c), AuditServiceProvider::recorderFactory($c), self::orgHolder($c)))
            ->set(
                GetCompanySettingsHandler::class,
                static fn (ContainerInterface $c): GetCompanySettingsHandler => new GetCompanySettingsHandler(
                    self::getUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                UpdateCompanySettingsHandler::class,
                static fn (ContainerInterface $c): UpdateCompanySettingsHandler => new UpdateCompanySettingsHandler(
                    self::updateUseCase($c),
                    self::json($c),
                ),
            )
            ->set(
                CompanySealRepositoryInterface::class,
                static function (ContainerInterface $c): CompanySealRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoCompanySealRepository($query, self::orgHolder($c), self::clock($c));
                },
            )
            ->set(
                CompanySealUseCaseInterface::class,
                static function (ContainerInterface $c): CompanySealUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new CompanySealUseCase(
                        self::sealRepository($c),
                        self::tx($c),
                        static fn (DatabaseQueryExecutorInterface $exec): CompanySealRepositoryInterface => new PdoCompanySealRepository($exec, $orgHolder, self::clock($c)),
                        AuditServiceProvider::recorderFactory($c),
                        $orgHolder,
                    );
                },
            )
            ->set(
                GetCompanySealHandler::class,
                static fn (ContainerInterface $c): GetCompanySealHandler => new GetCompanySealHandler(self::sealUseCase($c), self::json($c)),
            )
            ->set(
                PutCompanySealHandler::class,
                static fn (ContainerInterface $c): PutCompanySealHandler => new PutCompanySealHandler(self::sealUseCase($c), self::json($c)),
            )
            ->set(
                DeleteCompanySealHandler::class,
                static fn (ContainerInterface $c): DeleteCompanySealHandler => new DeleteCompanySealHandler(self::sealUseCase($c), self::json($c)),
            )
            ->set(
                CompanySettingsNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): CompanySettingsNotFoundExceptionHandler => new CompanySettingsNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                InvalidRegistrationNumberExceptionHandler::class,
                static fn (ContainerInterface $c): InvalidRegistrationNumberExceptionHandler => new InvalidRegistrationNumberExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                CompanyRouteRegistrar::class,
                static function (ContainerInterface $c): CompanyRouteRegistrar {
                    $get = $c->get(GetCompanySettingsHandler::class);
                    $update = $c->get(UpdateCompanySettingsHandler::class);
                    $getSeal = $c->get(GetCompanySealHandler::class);
                    $putSeal = $c->get(PutCompanySealHandler::class);
                    $deleteSeal = $c->get(DeleteCompanySealHandler::class);

                    if (!$get instanceof GetCompanySettingsHandler || !$update instanceof UpdateCompanySettingsHandler) {
                        throw new LogicException('Company settings handler services are invalid.');
                    }

                    if (!$getSeal instanceof GetCompanySealHandler || !$putSeal instanceof PutCompanySealHandler || !$deleteSeal instanceof DeleteCompanySealHandler) {
                        throw new LogicException('Company seal handler services are invalid.');
                    }

                    return new CompanyRouteRegistrar($get, $update, $getSeal, $putSeal, $deleteSeal);
                },
            );
    }

    private static function repository(ContainerInterface $c): CompanySettingsRepositoryInterface
    {
        $repo = $c->get(CompanySettingsRepositoryInterface::class);

        if (!$repo instanceof CompanySettingsRepositoryInterface) {
            throw new LogicException('Company settings repository service is invalid.');
        }

        return $repo;
    }

    private static function tx(ContainerInterface $c): DatabaseTransactionManagerInterface
    {
        $tx = $c->get(DatabaseTransactionManagerInterface::class);

        if (!$tx instanceof DatabaseTransactionManagerInterface) {
            throw new LogicException('Transaction manager service is invalid.');
        }

        return $tx;
    }

    /** @return Closure(DatabaseQueryExecutorInterface): CompanySettingsRepositoryInterface */
    private static function repositoryFactory(ContainerInterface $c): Closure
    {
        $orgHolder = self::orgHolder($c);
        $clock     = self::clock($c);

        return static fn (DatabaseQueryExecutorInterface $exec): CompanySettingsRepositoryInterface => new PdoCompanySettingsRepository($exec, $orgHolder, $clock);
    }

    private static function getUseCase(ContainerInterface $c): GetCompanySettingsUseCase
    {
        $u = $c->get(GetCompanySettingsUseCaseInterface::class);

        if (!$u instanceof GetCompanySettingsUseCase) {
            throw new LogicException('Get company settings use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateCompanySettingsUseCase
    {
        $u = $c->get(UpdateCompanySettingsUseCaseInterface::class);

        if (!$u instanceof UpdateCompanySettingsUseCase) {
            throw new LogicException('Update company settings use case service is invalid.');
        }

        return $u;
    }

    private static function sealRepository(ContainerInterface $c): CompanySealRepositoryInterface
    {
        $repo = $c->get(CompanySealRepositoryInterface::class);

        if (!$repo instanceof CompanySealRepositoryInterface) {
            throw new LogicException('Company seal repository service is invalid.');
        }

        return $repo;
    }

    private static function sealUseCase(ContainerInterface $c): CompanySealUseCase
    {
        $u = $c->get(CompanySealUseCaseInterface::class);

        if (!$u instanceof CompanySealUseCase) {
            throw new LogicException('Company seal use case service is invalid.');
        }

        return $u;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        $j = $c->get(JsonResponseFactory::class);

        if (!$j instanceof JsonResponseFactory) {
            throw new LogicException('JSON response factory service is invalid.');
        }

        return $j;
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        $p = $c->get(ProblemDetailsResponseFactory::class);

        if (!$p instanceof ProblemDetailsResponseFactory) {
            throw new LogicException('Problem details response factory service is invalid.');
        }

        return $p;
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

    private static function clock(ContainerInterface $c): ClockInterface
    {
        $clock = $c->get(ClockInterface::class);

        if (!$clock instanceof ClockInterface) {
            throw new LogicException('Clock service is invalid.');
        }

        return $clock;
    }
}
