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

describe('path tenancy app base (型B Phase 2)', () => {
  it('org slug at root → "/acme" API prefix and basename', () => {
    // Backend injects app-base=<install>/<slug>/; the same generic derivation
    // scopes both the router and every API call under the slug.
    expect(deriveApiBasePath('/acme/')).toBe('/acme')
    expect(deriveRouterBasename('/acme')).toBe('/acme')
  })

  it('org slug under a subdirectory install → "/invoice/acme"', () => {
    expect(deriveApiBasePath('/invoice/acme/')).toBe('/invoice/acme')
    expect(deriveRouterBasename('/invoice/acme')).toBe('/invoice/acme')
  })
})
