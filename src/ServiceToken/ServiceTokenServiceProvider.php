<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use LogicException;
use Nene2\Auth\TokenIssuerInterface;
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
use Psr\Container\ContainerInterface;

/**
 * Wires the service-token management domain: registry repository, request-time
 * revocation authorizer, issue/list/revoke use cases, handlers, not-found
 * exception handler, and the route registrar (ADR 0009 ops follow-up).
 */
final readonly class ServiceTokenServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ServiceTokenRepositoryInterface::class,
                static fn (ContainerInterface $c): ServiceTokenRepositoryInterface => new PdoServiceTokenRepository(self::executor($c), self::orgHolder($c)),
            )
            ->set(
                ServiceTokenAuthorizerInterface::class,
                static fn (ContainerInterface $c): ServiceTokenAuthorizerInterface => new PdoServiceTokenAuthorizer(self::executor($c)),
            )
            ->set(
                ListServiceTokensUseCaseInterface::class,
                static fn (ContainerInterface $c): ListServiceTokensUseCase => new ListServiceTokensUseCase(self::repository($c)),
            )
            ->set(
                IssueServiceTokenUseCaseInterface::class,
                static function (ContainerInterface $c): IssueServiceTokenUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new IssueServiceTokenUseCase(
                        self::resolve($c, TokenIssuerInterface::class),
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): ServiceTokenRepositoryInterface => new PdoServiceTokenRepository($exec, $orgHolder),
                        AuditServiceProvider::recorderFactory($c),
                        self::clock($c),
                        $orgHolder,
                    );
                },
            )
            ->set(
                RevokeServiceTokenUseCaseInterface::class,
                static function (ContainerInterface $c): RevokeServiceTokenUseCase {
                    $orgHolder = self::orgHolder($c);

                    return new RevokeServiceTokenUseCase(
                        self::resolve($c, DatabaseTransactionManagerInterface::class),
                        static fn (DatabaseQueryExecutorInterface $exec): ServiceTokenRepositoryInterface => new PdoServiceTokenRepository($exec, $orgHolder),
                        AuditServiceProvider::recorderFactory($c),
                        self::clock($c),
                        $orgHolder,
                    );
                },
            )
            ->set(
                ListServiceTokensHandler::class,
                static fn (ContainerInterface $c): ListServiceTokensHandler => new ListServiceTokensHandler(self::resolve($c, ListServiceTokensUseCaseInterface::class), self::json($c)),
            )
            ->set(
                IssueServiceTokenHandler::class,
                static fn (ContainerInterface $c): IssueServiceTokenHandler => new IssueServiceTokenHandler(self::resolve($c, IssueServiceTokenUseCaseInterface::class), self::json($c)),
            )
            ->set(
                RevokeServiceTokenHandler::class,
                static fn (ContainerInterface $c): RevokeServiceTokenHandler => new RevokeServiceTokenHandler(self::resolve($c, RevokeServiceTokenUseCaseInterface::class), self::json($c)),
            )
            ->set(
                ServiceTokenNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ServiceTokenNotFoundExceptionHandler => new ServiceTokenNotFoundExceptionHandler(self::problemDetails($c)),
            )
            ->set(
                ServiceTokenRouteRegistrar::class,
                static function (ContainerInterface $c): ServiceTokenRouteRegistrar {
                    $list = $c->get(ListServiceTokensHandler::class);
                    $issue = $c->get(IssueServiceTokenHandler::class);
                    $revoke = $c->get(RevokeServiceTokenHandler::class);

                    if (!$list instanceof ListServiceTokensHandler || !$issue instanceof IssueServiceTokenHandler || !$revoke instanceof RevokeServiceTokenHandler) {
                        throw new LogicException('Service token handler services are invalid.');
                    }

                    return new ServiceTokenRouteRegistrar($list, $issue, $revoke);
                },
            );
    }

    private static function repository(ContainerInterface $c): ServiceTokenRepositoryInterface
    {
        return self::resolve($c, ServiceTokenRepositoryInterface::class);
    }

    private static function executor(ContainerInterface $c): DatabaseQueryExecutorInterface
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

    private static function clock(ContainerInterface $c): ClockInterface
    {
        return self::resolve($c, ClockInterface::class);
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        return self::resolve($c, JsonResponseFactory::class);
    }

    private static function problemDetails(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        return self::resolve($c, ProblemDetailsResponseFactory::class);
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
}
