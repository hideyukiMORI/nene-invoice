import { useContext } from 'react'
import { I18nContext, type I18nContextValue } from './context'

export function useTranslation(): I18nContextValue {
  const ctx = useContext(I18nContext)
  if (ctx === null) {
    throw new Error('useTranslation must be used within I18nProvider')
  }
  return ctx
}
