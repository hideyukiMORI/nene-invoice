import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { routerBasename } from '@/shared/config/app-base'
import { HomeRedirect } from '@/app/home-redirect'
import { RequireRole } from '@/app/require-role'
import { AppShell } from '@/pages/layout'
import { AuditLogsPage } from '@/pages/audit-logs'
import { BankReconciliationPage } from '@/pages/bank-reconciliation'
import { ClientCreatePage } from '@/pages/client-create'
import { ClientEditPage } from '@/pages/client-edit'
import { ClientImportPage } from '@/pages/client-import'
import { ClientsPage } from '@/pages/clients'
import { CompanySettingsPage } from '@/pages/company-settings'
import { DashboardPage } from '@/pages/dashboard'
import { HelpPage } from '@/pages/help'
import { InvoiceCreatePage } from '@/pages/invoice-create'
import { ItemCreatePage } from '@/pages/item-create'
import { ItemEditPage } from '@/pages/item-edit'
import { ItemImportPage } from '@/pages/item-import'
import { ItemsPage } from '@/pages/items'
import { QuoteCreatePage } from '@/pages/quote-create'
import { RecurringPage } from '@/pages/recurring'
import { RecurringCreatePage } from '@/pages/recurring-create'
import { RecurringEditPage } from '@/pages/recurring-edit'
import { TemplateCreatePage } from '@/pages/template-create'
import { TemplateEditPage } from '@/pages/template-edit'
import { TemplatesPage } from '@/pages/templates'
import { QuoteDetailPage } from '@/pages/quote-detail'
import { QuotesPage } from '@/pages/quotes'
import { InvoiceDetailPage } from '@/pages/invoice-detail'
import { InvoicesPage } from '@/pages/invoices'
import { UsersPage } from '@/pages/users'
import { UserCreatePage } from '@/pages/user-create'
import { UserEditPage } from '@/pages/user-edit'
import { OrganizationsPage } from '@/pages/organizations'
import { OrganizationCreatePage } from '@/pages/organization-create'
import { ServiceTokensPage } from '@/pages/service-tokens'

const router = createBrowserRouter(
  [
    {
      path: '/',
      element: <AppShell />,
      children: [
        { index: true, element: <HomeRedirect /> },
        // Org-scoped screens need a tenant context; the org-less superadmin is
        // redirected to organization management (型B Phase 2 — RequireRole).
        {
          element: <RequireRole audience="org" />,
          children: [
            { path: 'dashboard', element: <DashboardPage /> },
            { path: 'invoices', element: <InvoicesPage /> },
            { path: 'invoices/new', element: <InvoiceCreatePage /> },
            { path: 'invoices/:id', element: <InvoiceDetailPage /> },
            { path: 'clients', element: <ClientsPage /> },
            { path: 'clients/new', element: <ClientCreatePage /> },
            { path: 'clients/import', element: <ClientImportPage /> },
            { path: 'clients/:id/edit', element: <ClientEditPage /> },
            { path: 'items', element: <ItemsPage /> },
            { path: 'items/new', element: <ItemCreatePage /> },
            { path: 'items/import', element: <ItemImportPage /> },
            { path: 'items/:id/edit', element: <ItemEditPage /> },
            { path: 'quotes', element: <QuotesPage /> },
            { path: 'quotes/new', element: <QuoteCreatePage /> },
            { path: 'quotes/:id', element: <QuoteDetailPage /> },
            { path: 'recurring', element: <RecurringPage /> },
            { path: 'recurring/new', element: <RecurringCreatePage /> },
            { path: 'recurring/:id/edit', element: <RecurringEditPage /> },
            { path: 'bank-reconciliation', element: <BankReconciliationPage /> },
            { path: 'templates', element: <TemplatesPage /> },
            { path: 'templates/new', element: <TemplateCreatePage /> },
            { path: 'templates/:id/edit', element: <TemplateEditPage /> },
            { path: 'settings', element: <CompanySettingsPage /> },
            { path: 'users', element: <UsersPage /> },
            { path: 'users/new', element: <UserCreatePage /> },
            { path: 'users/:id/edit', element: <UserEditPage /> },
            { path: 'audit-logs', element: <AuditLogsPage /> },
            { path: 'service-tokens', element: <ServiceTokensPage /> },
          ],
        },
        // Cross-tenant organization management is superadmin-only; a tenant
        // operator is redirected to their dashboard.
        {
          element: <RequireRole audience="superadmin" />,
          children: [
            { path: 'organizations', element: <OrganizationsPage /> },
            { path: 'organizations/new', element: <OrganizationCreatePage /> },
          ],
        },
        // Neutral (no tenant context, any role).
        { path: 'help', element: <HelpPage /> },
      ],
    },
    { path: '*', element: <HomeRedirect /> },
  ],
  { basename: routerBasename },
)

export function AppRouter() {
  return <RouterProvider router={router} />
}
