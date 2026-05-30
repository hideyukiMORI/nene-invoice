<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditRecorderInterface;
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

                    return new PdoCompanySettingsRepository($query, self::orgHolder($c));
                },
            )
            ->set(GetCompanySettingsUseCase::class, static fn (ContainerInterface $c): GetCompanySettingsUseCase => new GetCompanySettingsUseCase(self::repository($c), self::orgHolder($c)))
            ->set(UpdateCompanySettingsUseCase::class, static fn (ContainerInterface $c): UpdateCompanySettingsUseCase => new UpdateCompanySettingsUseCase(self::repository($c), self::audit($c), self::orgHolder($c)))
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
                    self::problemDetails($c),
                ),
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

                    if (!$get instanceof GetCompanySettingsHandler || !$update instanceof UpdateCompanySettingsHandler) {
                        throw new LogicException('Company settings handler services are invalid.');
                    }

                    return new CompanyRouteRegistrar($get, $update);
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

    private static function audit(ContainerInterface $c): AuditRecorderInterface
    {
        $recorder = $c->get(AuditRecorderInterface::class);

        if (!$recorder instanceof AuditRecorderInterface) {
            throw new LogicException('Audit recorder service is invalid.');
        }

        return $recorder;
    }

    private static function getUseCase(ContainerInterface $c): GetCompanySettingsUseCase
    {
        $u = $c->get(GetCompanySettingsUseCase::class);

        if (!$u instanceof GetCompanySettingsUseCase) {
            throw new LogicException('Get company settings use case service is invalid.');
        }

        return $u;
    }

    private static function updateUseCase(ContainerInterface $c): UpdateCompanySettingsUseCase
    {
        $u = $c->get(UpdateCompanySettingsUseCase::class);

        if (!$u instanceof UpdateCompanySettingsUseCase) {
            throw new LogicException('Update company settings use case service is invalid.');
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
}
