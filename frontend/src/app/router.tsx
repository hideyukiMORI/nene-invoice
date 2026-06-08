import { createBrowserRouter, Navigate, RouterProvider } from 'react-router-dom'
import { AppShell } from '@/pages/layout'
import { AuditLogsPage } from '@/pages/audit-logs'
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
import { ServiceTokensPage } from '@/pages/service-tokens'

const router = createBrowserRouter([
  {
    path: '/',
    element: <AppShell />,
    children: [
      { index: true, element: <Navigate to="/dashboard" replace /> },
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
      { path: 'templates', element: <TemplatesPage /> },
      { path: 'templates/new', element: <TemplateCreatePage /> },
      { path: 'templates/:id/edit', element: <TemplateEditPage /> },
      { path: 'settings', element: <CompanySettingsPage /> },
      { path: 'users', element: <UsersPage /> },
      { path: 'users/new', element: <UserCreatePage /> },
      { path: 'users/:id/edit', element: <UserEditPage /> },
      { path: 'audit-logs', element: <AuditLogsPage /> },
      { path: 'service-tokens', element: <ServiceTokensPage /> },
      { path: 'help', element: <HelpPage /> },
    ],
  },
  { path: '*', element: <Navigate to="/invoices" replace /> },
])

export function AppRouter() {
  return <RouterProvider router={router} />
}
