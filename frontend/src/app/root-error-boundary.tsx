import { Component, type ErrorInfo, type ReactNode } from 'react'
import { env } from '@/shared/config/env'
import { Button, Stack, Text } from '@/shared/ui'

interface RootErrorBoundaryProps {
  children: ReactNode
}

interface RootErrorBoundaryState {
  hasError: boolean
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
      return (
        <main className="mx-auto flex min-h-screen max-w-3xl items-center px-inline-md py-stack-xl">
          <Stack gap="md">
            <Text as="h1" variant="heading-md">
              予期しないエラーが発生しました
            </Text>
            <Text variant="muted">管理 UI でエラーが発生しました。</Text>
            <div>
              <Button variant="ghost" onClick={this.handleReset}>
                ホームへ戻る
              </Button>
            </div>
          </Stack>
        </main>
      )
    }

    return this.props.children
  }
}
