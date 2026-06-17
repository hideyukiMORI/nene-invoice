import { apiBasePath } from '@/shared/config/app-base'
import { AppError } from './errors'

/** Prefixes an absolute API path with the install base (ADR 0015); no-op at root. */
function apiUrl(path: string): string {
  return apiBasePath + path
}

/**
 * The only module that calls `fetch`. Transport only — no domain logic.
 *
 * The bearer token lives in memory (see frontend-standards: in-memory by default;
 * localStorage/cookie session needs an ADR). The auth flow sets it via
 * `setAuthToken`; it is lost on reload (fail-closed → re-login).
 */
let authToken: string | null = null
let sessionExpired = false
const authListeners = new Set<() => void>()

export function setAuthToken(token: string | null): void {
  authToken = token
  // A fresh sign-in clears any "session expired" notice on the login screen.
  if (token !== null) sessionExpired = false
  for (const listener of authListeners) listener()
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

/**
 * A 401 means the session expired or the token is invalid. Clear it so the
 * fail-closed auth shell shows the login screen, and flag the expiry so the
 * login form can say why. No-op when not signed in (e.g. a failed login attempt,
 * where the 401 is a credentials error to surface on the form instead).
 */
function handleUnauthorized(status: number): void {
  if (status === 401 && authToken !== null) {
    sessionExpired = true
    setAuthToken(null)
  }
}

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

/**
 * Silent re-authentication (ADR 0014). Exchanges the httpOnly refresh cookie for
 * a fresh in-memory access token, echoing the readable CSRF cookie in the
 * required header. De-duplicated: concurrent callers (the app-start probe and
 * any number of 401 retries) share one in-flight request. Resolves true when a
 * token was seated, false on any failure — the caller decides whether to fail
 * closed.
 */
let refreshInFlight: Promise<boolean> | null = null

function refreshAccessToken(): Promise<boolean> {
  if (refreshInFlight !== null) return refreshInFlight

  refreshInFlight = (async (): Promise<boolean> => {
    const csrf = readCookie(CSRF_COOKIE)
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (csrf !== null) headers[CSRF_HEADER] = csrf

    try {
      const response = await fetch(apiUrl('/auth/refresh'), { method: 'POST', headers })
      if (!response.ok) return false
      const token = (safeJsonParse(await response.text()) as { token?: unknown } | null)?.token
      if (typeof token !== 'string') return false
      setAuthToken(token)
      return true
    } catch {
      return false
    } finally {
      refreshInFlight = null
    }
  })()

  return refreshInFlight
}

/**
 * Attempt to restore a session on app start: after a full reload the in-memory
 * token is gone, but the refresh cookie may still be valid. Resolves true when
 * the session was restored (the auth shell then reveals the app instead of the
 * login screen).
 */
export function refreshSession(): Promise<boolean> {
  return refreshAccessToken()
}

/**
 * Server-side logout (ADR 0014): revokes the refresh-token family and clears the
 * cookies. Best-effort — a network failure still lets the caller clear the local
 * in-memory token.
 */
export async function revokeSession(): Promise<void> {
  const csrf = readCookie(CSRF_COOKIE)
  const headers: Record<string, string> = {}
  if (csrf !== null) headers[CSRF_HEADER] = csrf

  try {
    await fetch(apiUrl('/auth/logout'), { method: 'POST', headers })
  } catch {
    // best-effort; the caller clears the in-memory token regardless
  }
}

/**
 * On a 401 for a signed-in caller, try one silent refresh and report whether the
 * original request should be retried. Skips the auth endpoints (no recursion)
 * and the not-signed-in case (a failed login stays a credentials error).
 */
async function shouldRetryAfterRefresh(
  status: number,
  path: string,
  isRetry: boolean,
): Promise<boolean> {
  if (status !== 401 || isRetry || authToken === null) return false
  if (path === '/auth/refresh' || path === '/auth/logout') return false
  return refreshAccessToken()
}

type Json = Record<string, unknown>

async function request<T>(method: string, path: string, body?: Json, isRetry = false): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (authToken !== null) headers['Authorization'] = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(apiUrl(path), {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw AppError.transport('Network request failed')
  }

  if (response.status === 204) {
    return undefined as T
  }

  const text = await response.text()
  const parsed: unknown = text === '' ? null : safeJsonParse(text)

  if (!response.ok) {
    // Access token may have expired mid-session — try one silent refresh + replay.
    if (await shouldRetryAfterRefresh(response.status, path, isRetry)) {
      return request<T>(method, path, body, true)
    }
    handleUnauthorized(response.status)
    throw AppError.fromProblem(response.status, parsed)
  }

  return parsed as T
}

function safeJsonParse(text: string): unknown {
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

/** Fetches a binary resource and returns it as a Blob. Sends the Bearer token. */
async function requestBlob(path: string, isRetry = false): Promise<Blob> {
  const headers: Record<string, string> = {}
  if (authToken !== null) headers['Authorization'] = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(apiUrl(path), { method: 'GET', headers })
  } catch {
    throw AppError.transport('Network request failed')
  }

  if (!response.ok) {
    if (await shouldRetryAfterRefresh(response.status, path, isRetry)) {
      return requestBlob(path, true)
    }
    handleUnauthorized(response.status)
    const text = await response.text()
    throw AppError.fromProblem(response.status, text === '' ? null : safeJsonParse(text))
  }

  return response.blob()
}

/**
 * POSTs a raw CSV body and returns the parsed JSON report. Both 200 (accepted)
 * and 422 (rejected — the report carries the format/row errors) resolve with the
 * body; only auth / transport / 5xx throw. Used by template-only CSV import.
 */
async function postCsv<T>(path: string, csv: string, isRetry = false): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'text/csv' }
  if (authToken !== null) headers['Authorization'] = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(apiUrl(path), { method: 'POST', headers, body: csv })
  } catch {
    throw AppError.transport('Network request failed')
  }

  const text = await response.text()
  const parsed: unknown = text === '' ? null : safeJsonParse(text)

  if (response.status === 200 || response.status === 422) {
    return parsed as T
  }

  if (await shouldRetryAfterRefresh(response.status, path, isRetry)) {
    return postCsv<T>(path, csv, true)
  }
  handleUnauthorized(response.status)
  throw AppError.fromProblem(response.status, parsed)
}

export const apiClient = {
  get: <T>(path: string): Promise<T> => request<T>('GET', path),
  postCsv: <T>(path: string, csv: string): Promise<T> => postCsv<T>(path, csv),
  post: <T>(path: string, body?: Json): Promise<T> => request<T>('POST', path, body),
  put: <T>(path: string, body?: Json): Promise<T> => request<T>('PUT', path, body),
  patch: <T>(path: string, body?: Json): Promise<T> => request<T>('PATCH', path, body),
  delete: <T>(path: string): Promise<T> => request<T>('DELETE', path),
  getBlob: (path: string): Promise<Blob> => requestBlob(path),
} as const
