import {
  createNene2Transport,
  isNene2ClientError,
  type TokenStore,
} from '@hideyukimori/nene2-client'
import { apiBasePath, isPathTenancy } from '@/shared/config/app-base'
import { AppError } from './errors'

/**
 * The only module that talks to the API. Transport plumbing (fetch, the
 * `X-Authorization` mirror, 401 handling, single-flight silent-refresh, one
 * replay) is delegated to the fleet-standard `@hideyukimori/nene2-client`
 * transport (ADR 0008 seam). This module owns only the invoice-specific bits:
 * the in-memory token posture, the refresh mechanics (endpoint + CSRF cookie),
 * the promotion gate, and mapping the transport's errors to {@link AppError}.
 *
 * The exported surface (`apiClient`, `setAuthToken`, `hasAuthToken`,
 * `wasSessionExpired`, `subscribeAuthChange`, `refreshSession`, `revokeSession`)
 * is unchanged, so the ~40 call sites and `entities/auth` do not change.
 */

// ── In-memory session token ─────────────────────────────────────────────────
// The bearer token lives in memory on purpose (frontend-standards: in-memory by
// default; a localStorage/sessionStorage session would widen the XSS surface and
// needs its own ADR). It is lost on reload (fail-closed → re-login); ADR 0014
// silently restores it from the httpOnly refresh cookie. So the token store is
// an in-memory adapter, NOT `createSessionTokenStore` (which persists to
// sessionStorage).
let authToken: string | null = null
let sessionExpired = false
const authListeners = new Set<() => void>()

function notify(): void {
  for (const listener of authListeners) listener()
}

/** In-memory {@link TokenStore} the transport consults on every request. */
const tokenStore: TokenStore & { setToken(token: string): void } = {
  getToken: () => authToken,
  clearToken: () => {
    authToken = null
    notify()
  },
  setToken: (token: string) => {
    authToken = token
    // A fresh token (sign-in or silent refresh) clears any "session expired" notice.
    sessionExpired = false
    notify()
  },
}

export function setAuthToken(token: string | null): void {
  if (token === null) {
    tokenStore.clearToken()
  } else {
    tokenStore.setToken(token)
  }
}

export function hasAuthToken(): boolean {
  return authToken !== null
}

/**
 * True when the session ended because a request came back 401 (expired/invalid
 * token), as opposed to never having signed in. Lets the login screen explain
 * why the user landed back there. Cleared on the next successful sign-in.
 */
export function wasSessionExpired(): boolean {
  return sessionExpired
}

/** Subscribe to token / session changes (for `useSyncExternalStore`). */
export function subscribeAuthChange(listener: () => void): () => void {
  authListeners.add(listener)
  return () => {
    authListeners.delete(listener)
  }
}

// ── CSRF (double-submit cookie) ─────────────────────────────────────────────
const CSRF_COOKIE = 'ni_csrf'
const CSRF_HEADER = 'X-CSRF-Token'

/** Reads a non-httpOnly cookie value by name (the double-submit CSRF token). */
function readCookie(name: string): string | null {
  const prefix = `${name}=`
  for (const part of document.cookie.split('; ')) {
    if (part.startsWith(prefix)) return decodeURIComponent(part.slice(prefix.length))
  }
  return null
}

function safeJsonParse(text: string): unknown {
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

/**
 * Silent re-authentication mechanics (ADR 0008 `recoverAuth`): read the CSRF
 * cookie, exchange the httpOnly refresh cookie for a fresh access token, seat it.
 * The transport owns the orchestration (single-flight, one replay, no-recursion);
 * this owns only the invoice contract.
 *
 * The refresh POST uses a **bare** `fetch` — it must NOT go through the transport,
 * or it would await its own recovery.
 */
async function recoverAuth(): Promise<boolean> {
  const csrf = readCookie(CSRF_COOKIE)
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (csrf !== null) headers[CSRF_HEADER] = csrf

  try {
    const response = await fetch(apiBasePath + '/auth/refresh', { method: 'POST', headers })
    if (!response.ok) return false
    const token = (safeJsonParse(await response.text()) as { token?: unknown } | null)?.token
    if (typeof token !== 'string') return false
    tokenStore.setToken(token)
    return true
  } catch {
    return false
  }
}

const transport = createNene2Transport({
  baseUrl: apiBasePath,
  tokenStore,
  // Resolve `fetch` at call time rather than binding it once at module load: the
  // test setup patches `globalThis.fetch` via msw's `server.listen()` in a
  // `beforeAll`, which runs after this module is first imported (fleet pattern).
  fetch: (input, init) => globalThis.fetch(input, init),
  // A 401 on a signed-in request clears the token (transport default) and lands
  // the user on the fail-closed login screen; keep the "why" flag for the form.
  // The transport does not clear/fire for a token-less 401 (a failed login).
  onUnauthorized: () => {
    sessionExpired = true
    notify()
  },
  // Promotion gate (ADR 0008 / issue #38): silent refresh only where it is safe.
  // Under path-scoped tenancy the rotated `ni_refresh` is reissued at a
  // slug-stripped `Path` (#38), so a second refresh re-presents a consumed token
  // → server-side reuse defense revokes the family (hard logout). Single/host
  // mode has no slug, so refresh is safe there today — pass `recoverAuth`. Path
  // mode omits it (fail-closed one-shot) until #38 lands server-side.
  ...(isPathTenancy ? {} : { recoverAuth }),
})

/**
 * Attempt to restore a session on app start: after a full reload the in-memory
 * token is gone, but the refresh cookie may still be valid. Routes through
 * `transport.recover()` (never `recoverAuth` directly) so the boot probe and any
 * early 401-retry share one in-flight refresh — concurrent refreshes would trip
 * the rotation reuse defense. Resolves false in path mode (no `recoverAuth`).
 */
export function refreshSession(): Promise<boolean> {
  return transport.recover()
}

/**
 * Server-side logout (ADR 0014): revokes the refresh-token family and clears the
 * cookies. Best-effort bare fetch — a network failure still lets the caller clear
 * the local in-memory token.
 */
export async function revokeSession(): Promise<void> {
  const csrf = readCookie(CSRF_COOKIE)
  const headers: Record<string, string> = {}
  if (csrf !== null) headers[CSRF_HEADER] = csrf

  try {
    await fetch(apiBasePath + '/auth/logout', { method: 'POST', headers })
  } catch {
    // best-effort; the caller clears the in-memory token regardless
  }
}

type Json = Record<string, unknown>

/**
 * Maps the transport's `Nene2ClientError` (or any thrown value) to the invoice
 * {@link AppError} the call sites already handle (`.slug` / `.status`), so error
 * display is unchanged by the migration.
 */
function toAppError(error: unknown): never {
  if (isNene2ClientError(error)) {
    throw error.status > 0
      ? AppError.fromProblem(error.status, error.problem)
      : AppError.transport(error.message)
  }
  throw AppError.transport(error instanceof Error ? error.message : 'Request failed')
}

function wrap<T>(promise: Promise<T>): Promise<T> {
  return promise.catch(toAppError)
}

// 422 carries the CSV/bank-import rejection report as its body; hand it back
// rather than throw (mirrors the previous 200-or-422 branch).
const CSV_REPORT_STATUSES = { alsoOkStatuses: [422] } as const

export const apiClient = {
  get: <T>(path: string): Promise<T> => wrap(transport.get<T>(path)),
  post: <T>(path: string, body?: Json): Promise<T> => wrap(transport.post<T>(path, body)),
  put: <T>(path: string, body?: Json): Promise<T> => wrap(transport.put<T>(path, body)),
  patch: <T>(path: string, body?: Json): Promise<T> => wrap(transport.patch<T>(path, body)),
  delete: <T>(path: string): Promise<T> => wrap(transport.delete<T>(path)),
  postCsv: <T>(path: string, csv: string): Promise<T> =>
    wrap(transport.postCsv<T>(path, csv, CSV_REPORT_STATUSES)),
  postBytes: <T>(path: string, body: Blob): Promise<T> =>
    wrap(transport.postBytes<T>(path, body, CSV_REPORT_STATUSES)),
  getBlob: (path: string): Promise<Blob> =>
    wrap(transport.getBlob(path).then((download) => download.blob)),
} as const
