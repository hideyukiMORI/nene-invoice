import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { AppError } from '@/shared/api/errors'
import { env } from '@/shared/config/env'
import { I18nProvider } from '@/shared/i18n'
import { AuthGate } from './auth-gate'
import { RootErrorBoundary } from './root-error-boundary'

function createAppQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: (failureCount, error) =>
          failureCount < 2 && error instanceof AppError && error.isRetryable,
        refetchOnWindowFocus: env.isProd,
      },
      mutations: { retry: false },
    },
  })
}

export function AppProviders({ children }: { children: ReactNode }) {
  const [queryClient] = useState(createAppQueryClient)

  return (
    <I18nProvider>
      <QueryClientProvider client={queryClient}>
        <RootErrorBoundary>
          <AuthGate>{children}</AuthGate>
        </RootErrorBoundary>
      </QueryClientProvider>
    </I18nProvider>
  )
}
