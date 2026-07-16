import { describe, expect, it } from 'vitest'
import { deriveApiBasePath, deriveInstallBase, deriveRouterBasename } from './app-base'

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

describe('deriveInstallBase — install base without the tenant slug (promotion gate)', () => {
  it('strips the shell-appended /admin/ to reveal the install base', () => {
    expect(deriveInstallBase('/admin/')).toBe('')
    expect(deriveInstallBase('/invoice/admin/')).toBe('/invoice')
    expect(deriveInstallBase('/NeNeSuite/invoice/admin/')).toBe('/NeNeSuite/invoice')
  })

  it('returns "" when no base href is present (dev / single at root)', () => {
    expect(deriveInstallBase(null)).toBe('')
  })

  // The path-tenancy signal is `apiBasePath !== installBase`: in path mode the
  // app-base meta carries the slug while the asset base href does not, so the two
  // derivations diverge; in single/host mode they agree.
  it('a path-scoped slug makes the install base differ from the API base', () => {
    // path mode: base href = install only; meta app-base = install + slug
    expect(deriveApiBasePath('/invoice/acme/')).not.toBe(deriveInstallBase('/invoice/admin/'))
    // single subdir mode: they agree
    expect(deriveApiBasePath('/invoice/')).toBe(deriveInstallBase('/invoice/admin/'))
    // single root mode: both empty
    expect(deriveApiBasePath('/')).toBe(deriveInstallBase('/admin/'))
  })
})
