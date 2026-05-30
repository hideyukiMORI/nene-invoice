import { createBrowserRouter, Navigate, RouterProvider } from 'react-router-dom'
import { AppShell } from '@/pages/layout'
import { ClientCreatePage } from '@/pages/client-create'
import { ClientEditPage } from '@/pages/client-edit'
import { ClientsPage } from '@/pages/clients'
import { CompanySettingsPage } from '@/pages/company-settings'
import { DashboardPage } from '@/pages/dashboard'
import { InvoiceCreatePage } from '@/pages/invoice-create'
import { InvoiceDetailPage } from '@/pages/invoice-detail'
import { InvoicesPage } from '@/pages/invoices'

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
      { path: 'clients/:id/edit', element: <ClientEditPage /> },
      { path: 'settings', element: <CompanySettingsPage /> },
    ],
  },
  { path: '*', element: <Navigate to="/invoices" replace /> },
])

export function AppRouter() {
  return <RouterProvider router={router} />
}
