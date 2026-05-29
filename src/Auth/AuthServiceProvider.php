<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use LogicException;
use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Auth\TokenVerifierInterface;
use Nene2\Config\AppConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\User\PdoUserRepository;
use NeneInvoice\User\UserRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires authentication services: token issuer/verifier, user repository, HTTP
 * response factories, and the login use case / handler / route registrar.
 */
final readonly class AuthServiceProvider implements ServiceProviderInterface
{
    private const DEFAULT_DEV_SECRET = 'nene-invoice-dev-secret';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                LocalBearerTokenVerifier::class,
                static function (ContainerInterface $container): LocalBearerTokenVerifier {
                    $config = $container->get(AppConfig::class);

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new LocalBearerTokenVerifier($config->localJwtSecret ?? self::DEFAULT_DEV_SECRET);
                },
            )
            ->set(
                TokenIssuerInterface::class,
                static function (ContainerInterface $container): TokenIssuerInterface {
                    $verifier = $container->get(LocalBearerTokenVerifier::class);

                    if (!$verifier instanceof TokenIssuerInterface) {
                        throw new LogicException('Token issuer service is invalid.');
                    }

                    return $verifier;
                },
            )
            ->set(
                TokenVerifierInterface::class,
                static function (ContainerInterface $container): TokenVerifierInterface {
                    $verifier = $container->get(LocalBearerTokenVerifier::class);

                    if (!$verifier instanceof TokenVerifierInterface) {
                        throw new LogicException('Token verifier service is invalid.');
                    }

                    return $verifier;
                },
            )
            ->set(
                UserRepositoryInterface::class,
                static function (ContainerInterface $container): UserRepositoryInterface {
                    $query = $container->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('Database query executor service is invalid.');
                    }

                    return new PdoUserRepository($query);
                },
            )
            ->set(
                JsonResponseFactory::class,
                static function (ContainerInterface $container): JsonResponseFactory {
                    $psr17 = $container->get(Psr17Factory::class);

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    return new JsonResponseFactory($psr17, $psr17);
                },
            )
            ->set(
                ProblemDetailsResponseFactory::class,
                static function (ContainerInterface $container): ProblemDetailsResponseFactory {
                    $psr17 = $container->get(Psr17Factory::class);
                    $config = $container->get(AppConfig::class);

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new ProblemDetailsResponseFactory($psr17, $psr17, $config->problemDetailsBaseUrl);
                },
            )
            ->set(
                LoginUseCaseInterface::class,
                static function (ContainerInterface $container): LoginUseCaseInterface {
                    $users = $container->get(UserRepositoryInterface::class);
                    $tokenIssuer = $container->get(TokenIssuerInterface::class);

                    if (!$users instanceof UserRepositoryInterface) {
                        throw new LogicException('User repository service is invalid.');
                    }

                    if (!$tokenIssuer instanceof TokenIssuerInterface) {
                        throw new LogicException('Token issuer service is invalid.');
                    }

                    return new LoginUseCase($users, $tokenIssuer);
                },
            )
            ->set(
                LoginHandler::class,
                static function (ContainerInterface $container): LoginHandler {
                    $useCase = $container->get(LoginUseCaseInterface::class);
                    $json = $container->get(JsonResponseFactory::class);
                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$useCase instanceof LoginUseCaseInterface) {
                        throw new LogicException('Login use case service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JSON response factory service is invalid.');
                    }

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    return new LoginHandler($useCase, $json, $problemDetails);
                },
            )
            ->set(
                AuthRouteRegistrar::class,
                static function (ContainerInterface $container): AuthRouteRegistrar {
                    $loginHandler = $container->get(LoginHandler::class);

                    if (!$loginHandler instanceof LoginHandler) {
                        throw new LogicException('Login handler service is invalid.');
                    }

                    return new AuthRouteRegistrar($loginHandler);
                },
            );
    }
}
