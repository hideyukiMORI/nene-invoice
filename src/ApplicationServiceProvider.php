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
use NeneInvoice\Invoice\InvoiceNotFoundExceptionHandler;
use NeneInvoice\Invoice\InvoiceRouteRegistrar;
use NeneInvoice\Invoice\InvoiceValidationExceptionHandler;
use NeneInvoice\Invoice\QualifiedInvoiceIncompleteExceptionHandler;
use NeneInvoice\Organization\OrganizationNotFoundExceptionHandler;
use NeneInvoice\Organization\OrganizationRouteRegistrar;
use NeneInvoice\Organization\OrganizationSlugConflictExceptionHandler;
use NeneInvoice\Payment\PaymentRouteRegistrar;
use NeneInvoice\Payment\PaymentValidationExceptionHandler;
use NeneInvoice\Quote\InvalidStateTransitionExceptionHandler;
use NeneInvoice\Quote\QuoteNotFoundExceptionHandler;
use NeneInvoice\Quote\QuoteRouteRegistrar;
use NeneInvoice\Quote\QuoteValidationExceptionHandler;
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

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                self::ROUTE_REGISTRARS,
                static function (ContainerInterface $container): array {
                    $authRoutes = $container->get(AuthRouteRegistrar::class);
                    $auditRoutes = $container->get(AuditRouteRegistrar::class);
                    $organizationRoutes = $container->get(OrganizationRouteRegistrar::class);
                    $userRoutes = $container->get(UserRouteRegistrar::class);
                    $clientRoutes = $container->get(ClientRouteRegistrar::class);
                    $companyRoutes = $container->get(CompanyRouteRegistrar::class);
                    $quoteRoutes = $container->get(QuoteRouteRegistrar::class);
                    $invoiceRoutes = $container->get(InvoiceRouteRegistrar::class);
                    $paymentRoutes = $container->get(PaymentRouteRegistrar::class);

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

                    return [$authRoutes, $auditRoutes, $organizationRoutes, $userRoutes, $clientRoutes, $companyRoutes, $quoteRoutes, $invoiceRoutes, $paymentRoutes];
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
                    $companySettingsNotFound = $container->get(CompanySettingsNotFoundExceptionHandler::class);
                    $companyInvalidRegNumber = $container->get(CompanyInvalidRegistrationNumberExceptionHandler::class);
                    $quoteNotFound = $container->get(QuoteNotFoundExceptionHandler::class);
                    $quoteValidation = $container->get(QuoteValidationExceptionHandler::class);
                    $quoteInvalidTransition = $container->get(InvalidStateTransitionExceptionHandler::class);
                    $invoiceNotFound = $container->get(InvoiceNotFoundExceptionHandler::class);
                    $invoiceValidation = $container->get(InvoiceValidationExceptionHandler::class);
                    $qualifiedIncomplete = $container->get(QualifiedInvoiceIncompleteExceptionHandler::class);
                    $paymentValidation = $container->get(PaymentValidationExceptionHandler::class);

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

                    if (!$paymentValidation instanceof PaymentValidationExceptionHandler) {
                        throw new LogicException('Payment validation exception handler service is invalid.');
                    }

                    return [$orgNotFound, $orgSlugConflict, $userNotFound, $userEmailConflict, $roleNotAssignable, $cannotDeleteSelf, $clientNotFound, $invalidRegNumber, $companySettingsNotFound, $companyInvalidRegNumber, $quoteNotFound, $quoteValidation, $quoteInvalidTransition, $invoiceNotFound, $invoiceValidation, $qualifiedIncomplete, $paymentValidation];
                },
            );
    }
}
