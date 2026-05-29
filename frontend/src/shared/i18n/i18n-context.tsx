import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { I18nContext, type I18nContextValue } from './context'
import { enMessages } from './messages/en'
import { DEFAULT_LOCALE, jaCatalog, resolveLocale, type SupportedLocale } from './locales'
import { translate, type MessageCatalog } from './translate'

const STORAGE_KEY = 'nene-locale'

function detectLocale(): SupportedLocale {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored !== null) return resolveLocale(stored)
  } catch {
    // localStorage unavailable — fall through to navigator / default.
  }
  return resolveLocale(typeof navigator === 'undefined' ? DEFAULT_LOCALE : navigator.language)
}

function catalogFor(locale: SupportedLocale): MessageCatalog {
  return locale === 'en' ? enMessages : jaCatalog
}

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<SupportedLocale>(detectLocale)

  const setLocale = useCallback((next: SupportedLocale) => {
    setLocaleState(next)
    try {
      localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Persistence is best-effort.
    }
  }, [])

  const value = useMemo<I18nContextValue>(() => {
    const catalog = catalogFor(locale)
    return {
      locale,
      setLocale,
      t: (key, params) => translate(catalog, key, params),
    }
  }, [locale, setLocale])

  return <I18nContext value={value}>{children}</I18nContext>
}
