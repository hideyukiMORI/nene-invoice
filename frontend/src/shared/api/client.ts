import { AppError } from './errors'

/**
 * The only module that calls `fetch`. Transport only — no domain logic.
 *
 * The bearer token lives in memory (see frontend-standards: in-memory by default;
 * localStorage/cookie session needs an ADR). The auth flow sets it via
 * `setAuthToken`; it is lost on reload (fail-closed → re-login).
 */
let authToken: string | null = null
const authListeners = new Set<() => void>()

export function setAuthToken(token: string | null): void {
  authToken = token
  for (const listener of authListeners) listener()
}

export function hasAuthToken(): boolean {
  return authToken !== null
}

/** Subscribe to token changes (for `useSyncExternalStore` in the auth shell). */
export function subscribeAuthChange(listener: () => void): () => void {
  authListeners.add(listener)
  return () => {
    authListeners.delete(listener)
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

export const apiClient = {
  get: <T>(path: string): Promise<T> => request<T>('GET', path),
  post: <T>(path: string, body?: Json): Promise<T> => request<T>('POST', path, body),
  put: <T>(path: string, body?: Json): Promise<T> => request<T>('PUT', path, body),
  patch: <T>(path: string, body?: Json): Promise<T> => request<T>('PATCH', path, body),
  delete: <T>(path: string): Promise<T> => request<T>('DELETE', path),
} as const
