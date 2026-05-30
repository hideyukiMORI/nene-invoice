import { describe, expect, it } from 'vitest'
import { DEFAULT_LOCALE, LOCALES, resolveLocale } from './locales'

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
