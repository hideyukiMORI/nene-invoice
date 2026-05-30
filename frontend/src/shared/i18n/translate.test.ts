import { describe, expect, it } from 'vitest'
import { jaMessages } from './messages/ja'
import { translate, type MessageCatalog } from './translate'

describe('translate', () => {
  it('returns the value from the active catalog', () => {
    const catalog: MessageCatalog = { 'common.appName': 'Custom Name' }
    expect(translate(catalog, 'common.appName')).toBe('Custom Name')
  })

  it('falls back to the authoritative ja catalog for missing keys', () => {
    expect(translate({}, 'common.appName')).toBe(jaMessages['common.appName'])
    expect(translate({}, 'common.appName')).toBe('NeNe Invoice 管理')
  })

  it('interpolates {{placeholder}} params', () => {
    expect(translate(jaMessages, 'admin.account.signedInAs', { email: 'a@example.com' })).toBe(
      'a@example.com でログイン中',
    )
  })

  it('coerces numeric params to strings', () => {
    const catalog: MessageCatalog = { 'common.appName': '{{count}} items' }
    expect(translate(catalog, 'common.appName', { count: 3 })).toBe('3 items')
  })

  it('leaves the template unchanged when params are omitted', () => {
    expect(translate(jaMessages, 'admin.account.signedInAs')).toBe('{{email}} でログイン中')
  })

  it('keeps the placeholder when a referenced param is absent', () => {
    expect(translate(jaMessages, 'admin.account.signedInAs', {})).toBe('{{email}} でログイン中')
  })
})
