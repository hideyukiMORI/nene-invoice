import { expectCatalogParity } from '@hideyukimori/nene2-i18n/testing'
import { describe, expect, it } from 'vitest'
import { catalogs, DEFAULT_LOCALE, LOCALES, resolveLocale } from './locales'

// 規約 04 I18N-20: 全ロケール shape 一致ゲート（ja 権威）を共有実装で。
//
// 0.2.0 の expectCatalogParity は **vitest の test() を自己登録しない**（実測: dist/parity.js
// は違反時に throw するだけのアサーション）。トップレベルで呼ぶと collection エラーとして
// 落ちるので CI は止まるが、失敗が名前のあるテストに紐づかない。ここでは it() で包み、
// 落ちたときに何のゲートが落ちたかがレポートに出るようにする。
describe('catalog parity (nene2-i18n shared check)', () => {
  it('keeps every locale in shape with the ja authority', () => {
    expectCatalogParity(catalogs, {
      authority: 'ja',
      // 翻訳不能・同値が正のキーの列挙（数でなく列挙）。
      identicalAllowlist: [
        // ロケール自称名 — 言語切替 UI ではどのロケールでも自称で出す。
        'common.locale.ja',
        'common.locale.en',
        // 拡張子とエンコーディングの表記そのもの。
        'common.csvImport.dropSub',
        // ShortcutsOverlay / CommandPalette は「ja 見出し＋en 副題」を同時に描画する
        // ので、en カタログでも ja 見出しの値が正しい（messages/en.ts のコメント参照）。
        'admin.shortcuts.title',
        'admin.shortcuts.titleEn',
        'admin.commandPalette.title',
        'admin.commandPalette.titleEn',
      ],
    })
  })
})

describe('resolveLocale', () => {
  it('defaults to ja for null or undefined input', () => {
    expect(resolveLocale(null)).toBe('ja')
    expect(resolveLocale(undefined)).toBe('ja')
  })

  it('maps en-prefixed input to en, case-insensitively', () => {
    expect(resolveLocale('en')).toBe('en')
    expect(resolveLocale('en-US')).toBe('en')
    expect(resolveLocale('EN')).toBe('en')
  })

  it('maps any other input to ja', () => {
    expect(resolveLocale('ja')).toBe('ja')
    expect(resolveLocale('ja-JP')).toBe('ja')
    expect(resolveLocale('fr')).toBe('ja')
    expect(resolveLocale('')).toBe('ja')
  })
})

describe('locale metadata', () => {
  it('uses ja as the default locale', () => {
    expect(DEFAULT_LOCALE).toBe('ja')
  })

  it('exposes ja and en with stable label keys', () => {
    expect(LOCALES.map((l) => l.id)).toEqual(['ja', 'en'])
    expect(LOCALES.map((l) => l.labelKey)).toEqual(['common.locale.ja', 'common.locale.en'])
  })
})
