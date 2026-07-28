import { enMessages } from './messages/en'
import { jaMessages } from './messages/ja'
import type { MessageCatalog } from './translate'

export type SupportedLocale = 'ja' | 'en'

export const DEFAULT_LOCALE: SupportedLocale = 'ja'

export interface LocaleMeta {
  readonly id: SupportedLocale
  readonly labelKey: 'common.locale.ja' | 'common.locale.en'
}

export const LOCALES: readonly LocaleMeta[] = [
  { id: 'ja', labelKey: 'common.locale.ja' },
  { id: 'en', labelKey: 'common.locale.en' },
]

/** Resolve any input (navigator.language, stored value) to a supported locale. */
export function resolveLocale(input: string | null | undefined): SupportedLocale {
  if (input === null || input === undefined) return DEFAULT_LOCALE
  return input.toLowerCase().startsWith('en') ? 'en' : 'ja'
}

/** ja is the authoritative full catalog; en is loaded lazily by the provider. */
export const jaCatalog: MessageCatalog = jaMessages

/**
 * Every catalog, keyed by locale — the input to the shared parity check
 * (`@hideyukimori/nene2-i18n/testing`, see locales.test.ts). ja stays the
 * authority; the check enforces that en carries the same key set, so the
 * runtime ja fallback in `translate()` is a safety net rather than a licence
 * for en to drift behind.
 */
export const catalogs: Record<string, MessageCatalog> = { ja: jaMessages, en: enMessages }
