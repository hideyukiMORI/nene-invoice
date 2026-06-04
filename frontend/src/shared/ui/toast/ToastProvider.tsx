import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from '@/shared/i18n'
import { ToastContext } from './context'
import type { Toast, ToastInput } from './model'
import { ToastStack } from './ToastStack'

const DEFAULT_DURATION_MS = 5000

let toastCounter = 0
function nextToastId(): string {
  toastCounter += 1
  return `toast-${String(toastCounter)}`
}

/**
 * Hosts the 型3 toast queue and renders the stack. Toasts auto-dismiss after
 * {@link ToastInput.durationMs} (default 5s); manual close clears the timer.
 * Must sit inside the i18n provider — the close label is translated.
 */
export function ToastProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const [toasts, setToasts] = useState<readonly Toast[]>([])
  const timers = useRef(new Map<string, ReturnType<typeof setTimeout>>())

  const dismiss = useCallback((id: string) => {
    const timer = timers.current.get(id)
    if (timer !== undefined) {
      clearTimeout(timer)
      timers.current.delete(id)
    }
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  const showToast = useCallback(
    (input: ToastInput) => {
      const id = nextToastId()
      setToasts((current) => [...current, { ...input, id }])
      const timer = setTimeout(() => {
        dismiss(id)
      }, input.durationMs ?? DEFAULT_DURATION_MS)
      timers.current.set(id, timer)
    },
    [dismiss],
  )

  useEffect(() => {
    const map = timers.current
    return () => {
      for (const timer of map.values()) {
        clearTimeout(timer)
      }
      map.clear()
    }
  }, [])

  const value = useMemo(() => ({ showToast }), [showToast])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastStack toasts={toasts} onDismiss={dismiss} closeLabel={t('common.actions.close')} />
    </ToastContext.Provider>
  )
}
