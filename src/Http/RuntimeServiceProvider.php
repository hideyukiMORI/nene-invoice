<?php

declare(strict_types=1);

namespace NeneInvoice\Http;

use LogicException;
use Nene2\Auth\BearerTokenMiddleware;
use Nene2\Config\AppConfig;
use Nene2\Config\ConfigLoader;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\ResponseEmitter;
use Nene2\Http\RuntimeApplicationFactory;
use Nene2\Http\UtcClock;
use Nene2\Routing\Router;
use NeneInvoice\ApplicationServiceProvider;
use NeneInvoice\Audit\AuditServiceProvider;
use NeneInvoice\Auth\AuthServiceProvider;
use NeneInvoice\Auth\CapabilityMiddleware;
use NeneInvoice\Auth\OrgGuardMiddleware;
use NeneInvoice\Client\ClientServiceProvider;
use NeneInvoice\Company\CompanyServiceProvider;
use NeneInvoice\Dashboard\DashboardServiceProvider;
use NeneInvoice\DocumentSequence\DocumentSequenceServiceProvider;
use NeneInvoice\GatewaySettings\GatewaySettingsServiceProvider;
use NeneInvoice\Invoice\InvoiceServiceProvider;
use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadTokenServiceProvider;
use NeneInvoice\Item\ItemServiceProvider;
use NeneInvoice\LineItem\LineItemServiceProvider;
use NeneInvoice\Mailer\MailerServiceProvider;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use NeneInvoice\Organization\OrganizationServiceProvider;
use NeneInvoice\Organization\Resolution\CustomDomainResolutionStrategy;
use NeneInvoice\Organization\Resolution\EnvResolutionStrategy;
use NeneInvoice\Organization\Resolution\OrgResolverMiddleware;
use NeneInvoice\Organization\Resolution\PathPrefixResolutionStrategy;
use NeneInvoice\Organization\Resolution\SubdomainResolutionStrategy;
use NeneInvoice\Payment\PaymentServiceProvider;
use NeneInvoice\PaymentLink\PaymentLinkServiceProvider;
use NeneInvoice\Quote\QuoteServiceProvider;
use NeneInvoice\RecurringInvoice\RecurringInvoiceServiceProvider;
use NeneInvoice\ServiceApi\ServiceApiServiceProvider;
use NeneInvoice\ServiceApi\ServiceScopeMiddleware;
use NeneInvoice\ServiceToken\ServiceTokenServiceProvider;
use NeneInvoice\Template\TemplateServiceProvider;
use NeneInvoice\User\UserServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Wires the NENE2 HTTP runtime for NeNe Invoice: configuration, database
 * connectivity, bearer-token authentication, and the request handler.
 *
 * Tenant resolution and capability (RBAC) middleware are added in following PRs
 * by extending {@see RuntimeApplicationFactory} construction here.
 */
final readonly class RuntimeServiceProvider implements ServiceProviderInterface
{
    public const PROJECT_ROOT = 'nene-invoice.project_root';

    public function register(ContainerBuilder $builder): void
    {
        $builder->addProvider(new ApplicationServiceProvider());
        $builder->addProvider(new AuditServiceProvider());
        $builder->addProvider(new DashboardServiceProvider());
        $builder->addProvider(new InvoiceDownloadTokenServiceProvider());
        $builder->addProvider(new AuthServiceProvider());
        $builder->addProvider(new OrganizationServiceProvider());
        $builder->addProvider(new UserServiceProvider());
        $builder->addProvider(new ClientServiceProvider());
        $builder->addProvider(new ItemServiceProvider());
        $builder->addProvider(new CompanyServiceProvider());
        $builder->addProvider(new DocumentSequenceServiceProvider());
        $builder->addProvider(new LineItemServiceProvider());
        $builder->addProvider(new TemplateServiceProvider());
        $builder->addProvider(new QuoteServiceProvider());
        $builder->addProvider(new RecurringInvoiceServiceProvider());
        $builder->addProvider(new MailerServiceProvider());
        $builder->addProvider(new InvoiceServiceProvider());
        $builder->addProvider(new PaymentServiceProvider());
        $builder->addProvider(new ServiceApiServiceProvider());
        $builder->addProvider(new ServiceTokenServiceProvider());
        $builder->addProvider(new PaymentLinkServiceProvider());
        $builder->addProvider(new GatewaySettingsServiceProvider());

        $builder
            ->set(Psr17Factory::class, static fn (ContainerInterface $container): Psr17Factory => new Psr17Factory())
            ->set(
                ConfigLoader::class,
                static function (ContainerInterface $container): ConfigLoader {
                    $projectRoot = $container->get(self::PROJECT_ROOT);

                    if (!is_string($projectRoot) || $projectRoot === '') {
                        throw new LogicException('Project root service is invalid.');
                    }

                    return new ConfigLoader($projectRoot);
                },
            )
            ->set(
                AppConfig::class,
                static function (ContainerInterface $container): AppConfig {
                    $loader = $container->get(ConfigLoader::class);

                    if (!$loader instanceof ConfigLoader) {
                        throw new LogicException('Config loader service is invalid.');
                    }

                    return $loader->load();
                },
            )
            ->set(
                DatabaseConnectionFactoryInterface::class,
                static function (ContainerInterface $container): DatabaseConnectionFactoryInterface {
                    $config = $container->get(AppConfig::class);

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new PdoConnectionFactory($config->database);
                },
            )
            ->set(
                DatabaseQueryExecutorInterface::class,
                static function (ContainerInterface $container): DatabaseQueryExecutorInterface {
                    $connectionFactory = $container->get(DatabaseConnectionFactoryInterface::class);

                    if (!$connectionFactory instanceof DatabaseConnectionFactoryInterface) {
                        throw new LogicException('Database connection factory service is invalid.');
                    }

                    return new PdoDatabaseQueryExecutor($connectionFactory);
                },
            )
            ->set(
                ClockInterface::class,
                static fn (): ClockInterface => new UtcClock(),
            )
            ->set(
                DatabaseTransactionManagerInterface::class,
                static function (ContainerInterface $container): DatabaseTransactionManagerInterface {
                    $connectionFactory = $container->get(DatabaseConnectionFactoryInterface::class);

                    if (!$connectionFactory instanceof DatabaseConnectionFactoryInterface) {
                        throw new LogicException('Database connection factory service is invalid.');
                    }

                    return new PdoDatabaseTransactionManager($connectionFactory);
                },
            )
            ->set(
                DatabaseHealthCheck::class,
                static function (ContainerInterface $container): DatabaseHealthCheck {
                    $connectionFactory = $container->get(DatabaseConnectionFactoryInterface::class);

                    if (!$connectionFactory instanceof DatabaseConnectionFactoryInterface) {
                        throw new LogicException('Database connection factory service is invalid.');
                    }

                    return new DatabaseHealthCheck($connectionFactory);
                },
            )
            ->set(
                RuntimeApplicationFactory::class,
                static function (ContainerInterface $container): RuntimeApplicationFactory {
                    $psr17 = $container->get(Psr17Factory::class);

                    if (!$psr17 instanceof Psr17Factory) {
                        throw new LogicException('PSR-17 factory service is invalid.');
                    }

                    $config = $container->get(AppConfig::class);

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    $databaseHealthCheck = $container->get(DatabaseHealthCheck::class);

                    if (!$databaseHealthCheck instanceof DatabaseHealthCheck) {
                        throw new LogicException('Database health check service is invalid.');
                    }

                    $bearerTokenMiddleware = $container->get(BearerTokenMiddleware::class);

                    if (!$bearerTokenMiddleware instanceof BearerTokenMiddleware) {
                        throw new LogicException('Bearer token middleware service is invalid.');
                    }

                    $capabilityMiddleware = $container->get(CapabilityMiddleware::class);

                    if (!$capabilityMiddleware instanceof CapabilityMiddleware) {
                        throw new LogicException('Capability middleware service is invalid.');
                    }

                    $serviceScopeMiddleware = $container->get(ServiceScopeMiddleware::class);

                    if (!$serviceScopeMiddleware instanceof ServiceScopeMiddleware) {
                        throw new LogicException('Service scope middleware service is invalid.');
                    }

                    // --- Org resolution (ADR 0006): resolve tenant from the URL,
                    // store in the shared holder, and guard token org == resolved org. ---
                    $orgIdHolder = $container->get(ApplicationServiceProvider::ORG_ID_HOLDER);

                    if (!$orgIdHolder instanceof RequestScopedHolder) {
                        throw new LogicException('Org id holder service is invalid.');
                    }
                    /** @var RequestScopedHolder<int> $orgIdHolder */

                    $orgRepository = $container->get(OrganizationRepositoryInterface::class);

                    if (!$orgRepository instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('Organization repository service is invalid.');
                    }

                    $problemDetails = $container->get(ProblemDetailsResponseFactory::class);

                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    $mode   = self::env('TENANT_RESOLUTION', 'single');
                    $slug   = self::env('ORG_SLUG', '');
                    $domain = self::env('BASE_DOMAIN', 'localhost');

                    $strategy = match ($mode) {
                        'subdomain'     => new SubdomainResolutionStrategy($domain),
                        'path'          => new PathPrefixResolutionStrategy(),
                        'custom_domain' => new CustomDomainResolutionStrategy(),
                        default         => new EnvResolutionStrategy($slug),
                    };

                    // Sole-org fallback only in single (env) mode: a one-tenant
                    // install needs no ORG_SLUG.
                    $soleOrgFallback = !in_array($mode, ['subdomain', 'path', 'custom_domain'], true);

                    $orgResolverMiddleware = new OrgResolverMiddleware($orgIdHolder, $orgRepository, $problemDetails, $strategy, $soleOrgFallback);
                    $orgGuardMiddleware    = new OrgGuardMiddleware($problemDetails);

                    $routeRegistrars = $container->get(ApplicationServiceProvider::ROUTE_REGISTRARS);

                    if (!is_array($routeRegistrars) || !array_is_list($routeRegistrars)) {
                        throw new LogicException('Route registrars service is invalid.');
                    }

                    /** @var list<callable(Router): void> $routeRegistrars */

                    $exceptionHandlers = $container->get(ApplicationServiceProvider::EXCEPTION_HANDLERS);

                    if (!is_array($exceptionHandlers) || !array_is_list($exceptionHandlers)) {
                        throw new LogicException('Exception handlers service is invalid.');
                    }

                    /** @var list<DomainExceptionHandlerInterface> $exceptionHandlers */

                    return new RuntimeApplicationFactory(
                        responseFactory: $psr17,
                        streamFactory: $psr17,
                        domainExceptionHandlers: $exceptionHandlers,
                        routeRegistrars: $routeRegistrars,
                        authMiddleware: [$orgResolverMiddleware, $bearerTokenMiddleware, $orgGuardMiddleware, $capabilityMiddleware, $serviceScopeMiddleware],
                        healthChecks: [$databaseHealthCheck],
                        debug: $config->debug,
                    );
                },
            )
            ->set(
                RequestHandlerInterface::class,
                static function (ContainerInterface $container): RequestHandlerInterface {
                    $factory = $container->get(RuntimeApplicationFactory::class);

                    if (!$factory instanceof RuntimeApplicationFactory) {
                        throw new LogicException('Runtime application factory service is invalid.');
                    }

                    return $factory->create();
                },
            )
            ->set(ResponseEmitter::class, static fn (ContainerInterface $container): ResponseEmitter => new ResponseEmitter());
    }

    /**
     * Reads an environment variable loaded by the Dotenv ConfigLoader, falling
     * back to a default. Tenant-resolution settings are env-driven (ADR 0006).
     */
    private static function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
