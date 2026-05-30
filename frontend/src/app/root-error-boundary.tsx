import { Component, type ErrorInfo, type ReactNode } from 'react'
import { env } from '@/shared/config/env'
import { useTranslation } from '@/shared/i18n'
import { Button, Stack, Text } from '@/shared/ui'

interface RootErrorBoundaryProps {
  children: ReactNode
}

interface RootErrorBoundaryState {
  hasError: boolean
}

/**
 * Fallback UI rendered when the boundary trips. Split into a function component
 * so the translated copy can use {@link useTranslation} — the class boundary
 * itself cannot call hooks. The boundary sits inside `I18nProvider`
 * (see app/providers.tsx), so the i18n context is available here.
 */
function RootErrorFallback({ onReset }: { onReset: () => void }): ReactNode {
  const { t } = useTranslation()

  return (
    <main className="mx-auto flex min-h-screen max-w-3xl items-center px-inline-md py-stack-xl">
      <Stack gap="md">
        <Text as="h1" variant="heading-md">
          {t('admin.error.title')}
        </Text>
        <Text variant="muted">{t('admin.error.body')}</Text>
        <div>
          <Button variant="ghost" onClick={onReset}>
            {t('admin.error.home')}
          </Button>
        </div>
      </Stack>
    </main>
  )
}

/**
 * Error boundaries must be class components (React requirement) — the documented
 * exception to the function-component rule. Renders a safe fallback; never leaks
 * error internals to the user.
 */
export class RootErrorBoundary extends Component<RootErrorBoundaryProps, RootErrorBoundaryState> {
  override state: RootErrorBoundaryState = { hasError: false }

  static getDerivedStateFromError(): RootErrorBoundaryState {
    return { hasError: true }
  }

  override componentDidCatch(error: Error, info: ErrorInfo): void {
    if (env.isDev) {
      console.error('Root error boundary caught:', error, info)
    }
  }

  private readonly handleReset = (): void => {
    this.setState({ hasError: false })
    window.location.assign('/')
  }

  override render(): ReactNode {
    if (this.state.hasError) {
      return <RootErrorFallback onReset={this.handleReset} />
    }

    return this.props.children
  }
}
