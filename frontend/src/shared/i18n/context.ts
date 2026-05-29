import { createContext } from 'react'
import type { SupportedLocale } from './locales'
import type { MessageKey, TranslateParams } from './translate'

export interface I18nContextValue {
  readonly locale: SupportedLocale
  readonly setLocale: (locale: SupportedLocale) => void
  readonly t: (key: MessageKey, params?: TranslateParams) => string
}

export const I18nContext = createContext<I18nContextValue | null>(null)
