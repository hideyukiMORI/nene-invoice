import { jaMessages } from './messages/ja'

export type MessageKey = keyof typeof jaMessages
export type MessageCatalog = Partial<Record<MessageKey, string>>
export type TranslateParams = Record<string, string | number>

/**
 * Pure translation. Resolves the active catalog, falls back to the authoritative
 * ja catalog for missing keys, then interpolates `{{name}}` placeholders.
 */
export function translate(
  catalog: MessageCatalog,
  key: MessageKey,
  params?: TranslateParams,
): string {
  const template = catalog[key] ?? jaMessages[key]
  if (params === undefined) return template

  return template.replace(/\{\{(\w+)\}\}/g, (_match, name: string) => {
    const value = params[name]
    return value === undefined ? `{{${name}}}` : String(value)
  })
}
