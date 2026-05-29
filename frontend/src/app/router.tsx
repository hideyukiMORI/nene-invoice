import { createBrowserRouter, Navigate, RouterProvider } from 'react-router-dom'
import { AppShell } from '@/pages/layout'
import { ClientCreatePage } from '@/pages/client-create'
import { ClientsPage } from '@/pages/clients'
import { InvoiceCreatePage } from '@/pages/invoice-create'
import { InvoiceDetailPage } from '@/pages/invoice-detail'
import { InvoicesPage } from '@/pages/invoices'

const router = createBrowserRouter([
  {
    path: '/',
    element: <AppShell />,
    children: [
      { index: true, element: <Navigate to="/invoices" replace /> },
      { path: 'invoices', element: <InvoicesPage /> },
      { path: 'invoices/new', element: <InvoiceCreatePage /> },
      { path: 'invoices/:id', element: <InvoiceDetailPage /> },
      { path: 'clients', element: <ClientsPage /> },
      { path: 'clients/new', element: <ClientCreatePage /> },
    ],
  },
  { path: '*', element: <Navigate to="/invoices" replace /> },
])

export function AppRouter() {
  return <RouterProvider router={router} />
}
