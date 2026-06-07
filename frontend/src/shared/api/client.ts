import { AppError } from './errors'

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

type Json = Record<string, unknown>

async function request<T>(method: string, path: string, body?: Json): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (authToken !== null) headers['Authorization'] = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(path, {
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
async function requestBlob(path: string): Promise<Blob> {
  const headers: Record<string, string> = {}
  if (authToken !== null) headers['Authorization'] = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(path, { method: 'GET', headers })
  } catch {
    throw AppError.transport('Network request failed')
  }

  if (!response.ok) {
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
async function postCsv<T>(path: string, csv: string): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'text/csv' }
  if (authToken !== null) headers['Authorization'] = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(path, { method: 'POST', headers, body: csv })
  } catch {
    throw AppError.transport('Network request failed')
  }

  const text = await response.text()
  const parsed: unknown = text === '' ? null : safeJsonParse(text)

  if (response.status === 200 || response.status === 422) {
    return parsed as T
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
