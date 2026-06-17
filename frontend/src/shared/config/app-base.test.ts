import { describe, expect, it } from 'vitest'
import { deriveApiBasePath, deriveRouterBasename } from './app-base'

describe('install base derivation (ADR 0015)', () => {
  it('document root → empty API prefix and "/" basename', () => {
    expect(deriveApiBasePath('/')).toBe('')
    expect(deriveRouterBasename('')).toBe('/')
  })

  it('subdirectory → "/invoice" prefix and basename', () => {
    expect(deriveApiBasePath('/invoice/')).toBe('/invoice')
    expect(deriveRouterBasename('/invoice')).toBe('/invoice')
  })

  it('nested suite path', () => {
    expect(deriveApiBasePath('/NeNeSuite/invoice/')).toBe('/NeNeSuite/invoice')
  })

  it('missing or malformed meta falls back to root', () => {
    expect(deriveApiBasePath(null)).toBe('')
    expect(deriveApiBasePath('')).toBe('')
    // Not an absolute path → ignored (defensive).
    expect(deriveApiBasePath('invoice/')).toBe('')
  })
})
