<?php

declare(strict_types=1);

namespace NeneInvoice;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneInvoice\Audit\AuditRouteRegistrar;
use NeneInvoice\Auth\AuthRouteRegistrar;
use NeneInvoice\Client\ClientNotFoundExceptionHandler;
use NeneInvoice\Client\ClientRouteRegistrar;
use NeneInvoice\Client\InvalidRegistrationNumberExceptionHandler;
use NeneInvoice\Company\CompanyRouteRegistrar;
use NeneInvoice\Company\CompanySettingsNotFoundExceptionHandler;
use NeneInvoice\Company\InvalidRegistrationNumberExceptionHandler as CompanyInvalidRegistrationNumberExceptionHandler;
use NeneInvoice\Dashboard\DashboardRouteRegistrar;
use NeneInvoice\Invoice\InvoiceEmailExceptionHandler;
use NeneInvoice\Invoice\InvoiceNotFoundExceptionHandler;
use NeneInvoice\Invoice\InvoiceRouteRegistrar;
use NeneInvoice\Invoice\InvoiceValidationExceptionHandler;
use NeneInvoice\Invoice\QualifiedInvoiceIncompleteExceptionHandler;
use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadTokenRouteRegistrar;
use NeneInvoice\Item\ItemNotFoundExceptionHandler;
use NeneInvoice\Item\ItemRouteRegistrar;
use NeneInvoice\LineItem\LineItemRouteRegistrar;
use NeneInvoice\Organization\OrganizationNotFoundExceptionHandler;
use NeneInvoice\Organization\OrganizationRouteRegistrar;
use NeneInvoice\Organization\OrganizationSlugConflictExceptionHandler;
use NeneInvoice\Payment\PaymentExceedsOutstandingExceptionHandler;
use NeneInvoice\Payment\PaymentNotFoundExceptionHandler;
use NeneInvoice\Payment\PaymentRouteRegistrar;
use NeneInvoice\Payment\PaymentValidationExceptionHandler;
use NeneInvoice\Quote\InvalidStateTransitionExceptionHandler;
use NeneInvoice\Quote\QuoteNotFoundExceptionHandler;
use NeneInvoice\Quote\QuoteRouteRegistrar;
use NeneInvoice\Quote\QuoteValidationExceptionHandler;
use NeneInvoice\ServiceApi\ServiceApiRouteRegistrar;
use NeneInvoice\ServiceToken\ServiceTokenNotFoundExceptionHandler;
use NeneInvoice\ServiceToken\ServiceTokenRouteRegistrar;
use NeneInvoice\Template\TemplateNotFoundExceptionHandler;
use NeneInvoice\Template\TemplateRouteRegistrar;
use NeneInvoice\User\CannotDeleteSelfExceptionHandler;
use NeneInvoice\User\RoleNotAssignableExceptionHandler;
use NeneInvoice\User\UserEmailConflictExceptionHandler;
use NeneInvoice\User\UserNotFoundExceptionHandler;
use NeneInvoice\User\UserRouteRegistrar;
use Psr\Container\ContainerInterface;

/**
 * Aggregates the application's route registrars and domain exception handlers.
 *
 * `GET /health` is provided by the framework. Per-domain providers
 * (Organization, Auth, User, Client, …) register their route registrars in the
 * container; this provider collects them into the list the runtime consumes.
 */
final readonly class ApplicationServiceProvider implements ServiceProviderInterface
{
    public const ROUTE_REGISTRARS = 'nene-invoice.route_registrars';
    public const EXCEPTION_HANDLERS = 'nene-invoice.exception_handlers';

    /**
     * Container key for the shared RequestScopedHolder<int> carrying the resolved
     * organization id. OrgResolverMiddleware writes it; every Pdo*Repository reads
     * it to scope queries. One instance per request (shared-nothing model).
     */
    public const ORG_ID_HOLDER = 'nene-invoice.org_id_holder';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                self::ORG_ID_HOLDER,
                static function (): \Nene2\Http\RequestScopedHolder {
                    /** @var \Nene2\Http\RequestScopedHolder<int> */
                    return new \Nene2\Http\RequestScopedHolder();
                },
            )
            ->set(
                self::ROUTE_REGISTRARS,
                static function (ContainerInterface $container): array {
                    $dashboardRoutes = $container->get(DashboardRouteRegistrar::class);
                    $authRoutes = $container->get(AuthRouteRegistrar::class);
                    $auditRoutes = $container->get(AuditRouteRegistrar::class);
                    $organizationRoutes = $container->get(OrganizationRouteRegistrar::class);
                    $userRoutes = $container->get(UserRouteRegistrar::class);
                    $clientRoutes = $container->get(ClientRouteRegistrar::class);
                    $companyRoutes = $container->get(CompanyRouteRegistrar::class);
                    $quoteRoutes = $container->get(QuoteRouteRegistrar::class);
                    $invoiceRoutes = $container->get(InvoiceRouteRegistrar::class);
                    $paymentRoutes = $container->get(PaymentRouteRegistrar::class);
                    $serviceApiRoutes = $container->get(ServiceApiRouteRegistrar::class);
                    $downloadTokenRoutes = $container->get(InvoiceDownloadTokenRouteRegistrar::class);
                    $lineItemRoutes = $container->get(LineItemRouteRegistrar::class);
                    $itemRoutes = $container->get(ItemRouteRegistrar::class);
                    $templateRoutes = $container->get(TemplateRouteRegistrar::class);
                    $serviceTokenRoutes = $container->get(ServiceTokenRouteRegistrar::class);

                    if (!$dashboardRoutes instanceof DashboardRouteRegistrar) {
                        throw new LogicException('Dashboard route registrar service is invalid.');
                    }

                    if (!$authRoutes instanceof AuthRouteRegistrar) {
                        throw new LogicException('Auth route registrar service is invalid.');
                    }

                    if (!$auditRoutes instanceof AuditRouteRegistrar) {
                        throw new LogicException('Audit route registrar service is invalid.');
                    }

                    if (!$organizationRoutes instanceof OrganizationRouteRegistrar) {
                        throw new LogicException('Organization route registrar service is invalid.');
                    }

                    if (!$userRoutes instanceof UserRouteRegistrar) {
                        throw new LogicException('User route registrar service is invalid.');
                    }

                    if (!$clientRoutes instanceof ClientRouteRegistrar) {
                        throw new LogicException('Client route registrar service is invalid.');
                    }

                    if (!$companyRoutes instanceof CompanyRouteRegistrar) {
                        throw new LogicException('Company route registrar service is invalid.');
                    }

                    if (!$quoteRoutes instanceof QuoteRouteRegistrar) {
                        throw new LogicException('Quote route registrar service is invalid.');
                    }

                    if (!$invoiceRoutes instanceof InvoiceRouteRegistrar) {
                        throw new LogicException('Invoice route registrar service is invalid.');
                    }

                    if (!$paymentRoutes instanceof PaymentRouteRegistrar) {
                        throw new LogicException('Payment route registrar service is invalid.');
                    }

                    if (!$serviceApiRoutes instanceof ServiceApiRouteRegistrar) {
                        throw new LogicException('Service API route registrar service is invalid.');
                    }

                    if (!$downloadTokenRoutes instanceof InvoiceDownloadTokenRouteRegistrar) {
                        throw new LogicException('Invoice download token route registrar service is invalid.');
                    }

                    if (!$lineItemRoutes instanceof LineItemRouteRegistrar) {
                        throw new LogicException('Line item route registrar service is invalid.');
                    }

                    if (!$itemRoutes instanceof ItemRouteRegistrar) {
                        throw new LogicException('Item route registrar service is invalid.');
                    }

                    if (!$templateRoutes instanceof TemplateRouteRegistrar) {
                        throw new LogicException('Template route registrar service is invalid.');
                    }

                    if (!$serviceTokenRoutes instanceof ServiceTokenRouteRegistrar) {
                        throw new LogicException('Service token route registrar service is invalid.');
                    }

                    return [$dashboardRoutes, $authRoutes, $auditRoutes, $organizationRoutes, $userRoutes, $clientRoutes, $companyRoutes, $quoteRoutes, $invoiceRoutes, $paymentRoutes, $serviceApiRoutes, $downloadTokenRoutes, $lineItemRoutes, $itemRoutes, $templateRoutes, $serviceTokenRoutes];
                },
            )
            ->set(
                self::EXCEPTION_HANDLERS,
                static function (ContainerInterface $container): array {
                    $orgNotFound = $container->get(OrganizationNotFoundExceptionHandler::class);
                    $orgSlugConflict = $container->get(OrganizationSlugConflictExceptionHandler::class);
                    $userNotFound = $container->get(UserNotFoundExceptionHandler::class);
                    $userEmailConflict = $container->get(UserEmailConflictExceptionHandler::class);
                    $roleNotAssignable = $container->get(RoleNotAssignableExceptionHandler::class);
                    $cannotDeleteSelf = $container->get(CannotDeleteSelfExceptionHandler::class);
                    $clientNotFound = $container->get(ClientNotFoundExceptionHandler::class);
                    $invalidRegNumber = $container->get(InvalidRegistrationNumberExceptionHandler::class);
                    $itemNotFound = $container->get(ItemNotFoundExceptionHandler::class);
                    $templateNotFound = $container->get(TemplateNotFoundExceptionHandler::class);
                    $companySettingsNotFound = $container->get(CompanySettingsNotFoundExceptionHandler::class);
                    $companyInvalidRegNumber = $container->get(CompanyInvalidRegistrationNumberExceptionHandler::class);
                    $quoteNotFound = $container->get(QuoteNotFoundExceptionHandler::class);
                    $quoteValidation = $container->get(QuoteValidationExceptionHandler::class);
                    $quoteInvalidTransition = $container->get(InvalidStateTransitionExceptionHandler::class);
                    $invoiceNotFound = $container->get(InvoiceNotFoundExceptionHandler::class);
                    $invoiceValidation = $container->get(InvoiceValidationExceptionHandler::class);
                    $qualifiedIncomplete = $container->get(QualifiedInvoiceIncompleteExceptionHandler::class);
                    $invoiceEmail = $container->get(InvoiceEmailExceptionHandler::class);
                    $paymentValidation = $container->get(PaymentValidationExceptionHandler::class);
                    $paymentExceeds = $container->get(PaymentExceedsOutstandingExceptionHandler::class);
                    $paymentNotFound = $container->get(PaymentNotFoundExceptionHandler::class);
                    $serviceTokenNotFound = $container->get(ServiceTokenNotFoundExceptionHandler::class);

                    if (!$orgNotFound instanceof OrganizationNotFoundExceptionHandler) {
                        throw new LogicException('Organization not-found exception handler service is invalid.');
                    }

                    if (!$orgSlugConflict instanceof OrganizationSlugConflictExceptionHandler) {
                        throw new LogicException('Organization slug-conflict exception handler service is invalid.');
                    }

                    if (!$userNotFound instanceof UserNotFoundExceptionHandler) {
                        throw new LogicException('User not-found exception handler service is invalid.');
                    }

                    if (!$userEmailConflict instanceof UserEmailConflictExceptionHandler) {
                        throw new LogicException('User email-conflict exception handler service is invalid.');
                    }

                    if (!$roleNotAssignable instanceof RoleNotAssignableExceptionHandler) {
                        throw new LogicException('Role not-assignable exception handler service is invalid.');
                    }

                    if (!$cannotDeleteSelf instanceof CannotDeleteSelfExceptionHandler) {
                        throw new LogicException('Cannot-delete-self exception handler service is invalid.');
                    }

                    if (!$clientNotFound instanceof ClientNotFoundExceptionHandler) {
                        throw new LogicException('Client not-found exception handler service is invalid.');
                    }

                    if (!$invalidRegNumber instanceof InvalidRegistrationNumberExceptionHandler) {
                        throw new LogicException('Invalid registration number exception handler service is invalid.');
                    }

                    if (!$itemNotFound instanceof ItemNotFoundExceptionHandler) {
                        throw new LogicException('Item not-found exception handler service is invalid.');
                    }

                    if (!$templateNotFound instanceof TemplateNotFoundExceptionHandler) {
                        throw new LogicException('Template not-found exception handler service is invalid.');
                    }

                    if (!$companySettingsNotFound instanceof CompanySettingsNotFoundExceptionHandler) {
                        throw new LogicException('Company settings not-found exception handler service is invalid.');
                    }

                    if (!$companyInvalidRegNumber instanceof CompanyInvalidRegistrationNumberExceptionHandler) {
                        throw new LogicException('Company invalid registration number exception handler service is invalid.');
                    }

                    if (!$quoteNotFound instanceof QuoteNotFoundExceptionHandler) {
                        throw new LogicException('Quote not-found exception handler service is invalid.');
                    }

                    if (!$quoteValidation instanceof QuoteValidationExceptionHandler) {
                        throw new LogicException('Quote validation exception handler service is invalid.');
                    }

                    if (!$quoteInvalidTransition instanceof InvalidStateTransitionExceptionHandler) {
                        throw new LogicException('Quote invalid state transition exception handler service is invalid.');
                    }

                    if (!$invoiceNotFound instanceof InvoiceNotFoundExceptionHandler) {
                        throw new LogicException('Invoice not-found exception handler service is invalid.');
                    }

                    if (!$invoiceValidation instanceof InvoiceValidationExceptionHandler) {
                        throw new LogicException('Invoice validation exception handler service is invalid.');
                    }

                    if (!$qualifiedIncomplete instanceof QualifiedInvoiceIncompleteExceptionHandler) {
                        throw new LogicException('Qualified invoice incomplete exception handler service is invalid.');
                    }

                    if (!$invoiceEmail instanceof InvoiceEmailExceptionHandler) {
                        throw new LogicException('Invoice email exception handler service is invalid.');
                    }

                    if (!$paymentValidation instanceof PaymentValidationExceptionHandler) {
                        throw new LogicException('Payment validation exception handler service is invalid.');
                    }

                    if (!$paymentExceeds instanceof PaymentExceedsOutstandingExceptionHandler) {
                        throw new LogicException('Payment exceeds-outstanding exception handler service is invalid.');
                    }

                    if (!$paymentNotFound instanceof PaymentNotFoundExceptionHandler) {
                        throw new LogicException('Payment not-found exception handler service is invalid.');
                    }

                    if (!$serviceTokenNotFound instanceof ServiceTokenNotFoundExceptionHandler) {
                        throw new LogicException('Service token not-found exception handler service is invalid.');
                    }

                    return [$orgNotFound, $orgSlugConflict, $userNotFound, $userEmailConflict, $roleNotAssignable, $cannotDeleteSelf, $clientNotFound, $invalidRegNumber, $itemNotFound, $templateNotFound, $companySettingsNotFound, $companyInvalidRegNumber, $quoteNotFound, $quoteValidation, $quoteInvalidTransition, $invoiceNotFound, $invoiceValidation, $qualifiedIncomplete, $invoiceEmail, $paymentValidation, $paymentExceeds, $paymentNotFound, $serviceTokenNotFound];
                },
            );
    }
}
