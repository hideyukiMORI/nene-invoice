import type { components } from './schema.gen'

type ProblemDetailsBody = components['schemas']['ProblemDetails'] & {
  errors?: { field?: string; code?: string }[]
}

/**
 * The single error type thrown across the client boundary. Built from an
 * RFC 9457 `application/problem+json` body (or a transport failure). Features map
 * `slug` / `status` to user-facing messages — they never parse responses themselves.
 */
export class AppError extends Error {
  readonly status: number
  /** Problem Details `type` reduced to its slug (e.g. `validation-failed`). */
  readonly slug: string
  readonly detail: string | undefined
  /** Per-field validation errors, when the API provides them. */
  readonly fieldErrors: { field?: string; code?: string }[]

  constructor(params: {
    status: number
    slug: string
    title: string
    detail?: string | undefined
    fieldErrors?: { field?: string; code?: string }[]
  }) {
    super(params.title)
    this.name = 'AppError'
    this.status = params.status
    this.slug = params.slug
    this.detail = params.detail
    this.fieldErrors = params.fieldErrors ?? []
  }

  /** 5xx and 408/429 are worth retrying; 4xx generally are not. */
  get isRetryable(): boolean {
    return this.status >= 500 || this.status === 408 || this.status === 429
  }

  get isUnauthorized(): boolean {
    return this.status === 401
  }

  get isForbidden(): boolean {
    return this.status === 403
  }

  static fromProblem(status: number, body: unknown): AppError {
    const problem = (typeof body === 'object' && body !== null ? body : {}) as ProblemDetailsBody
    const type = typeof problem.type === 'string' ? problem.type : ''
    const slug = type.replace(/\/+$/, '').split('/').pop() ?? 'error'

    return new AppError({
      status,
      slug: slug === '' ? 'error' : slug,
      title: typeof problem.title === 'string' ? problem.title : `HTTP ${String(status)}`,
      detail: typeof problem.detail === 'string' ? problem.detail : undefined,
      fieldErrors: Array.isArray(problem.errors) ? problem.errors : [],
    })
  }

  static transport(message: string): AppError {
    return new AppError({ status: 0, slug: 'network-error', title: message })
  }
}
