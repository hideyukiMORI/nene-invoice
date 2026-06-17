/**
 * Install-location awareness for the SPA (ADR 0015).
 *
 * The backend serves the app shell through the front controller and injects the
 * detected install base as `<meta name="app-base" content="<base>/">` (`/` at the
 * document root, `/invoice/` under a subdirectory). The frontend reads it once to
 * (a) prefix every absolute API path and (b) set the React Router `basename`, so
 * one build runs at any path without a rebuild.
 *
 * In dev (Vite serves index.html with no injected meta) the base resolves to the
 * document root, and the Vite proxy forwards `/auth` · `/admin` to the API.
 */

/** Normalizes the meta content to the API prefix: '' at root, '/invoice' under a subdir. */
export function deriveApiBasePath(metaContent: string | null): string {
  if (typeof metaContent !== 'string' || !metaContent.startsWith('/')) {
    return ''
  }

  const trimmed = metaContent.replace(/\/+$/, '')

  return trimmed
}

/** React Router basename: '/' at root, '/invoice' under a subdir. */
export function deriveRouterBasename(apiBasePath: string): string {
  return apiBasePath === '' ? '/' : apiBasePath
}

function readMetaBase(): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  return document.querySelector('meta[name="app-base"]')?.getAttribute('content') ?? null
}

/** URL prefix prepended to absolute API paths ('' at the document root). */
export const apiBasePath: string = deriveApiBasePath(readMetaBase())

/** Mount point for the SPA router ('/' at the document root). */
export const routerBasename: string = deriveRouterBasename(apiBasePath)
