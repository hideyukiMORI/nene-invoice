import { describe, expect, it } from 'vitest'
import { AppError } from './errors'

describe('AppError.fromProblem', () => {
  it('reduces the Problem Details type URL to its trailing slug', () => {
    const error = AppError.fromProblem(422, {
      type: 'https://errors.example.com/validation-failed',
      title: 'Validation failed',
    })
    expect(error.slug).toBe('validation-failed')
    expect(error.status).toBe(422)
    expect(error.message).toBe('Validation failed')
  })

  it('strips trailing slashes before taking the last segment', () => {
    const error = AppError.fromProblem(409, { type: '/problems/conflict///' })
    expect(error.slug).toBe('conflict')
  })

  it('falls back to the "error" slug when type is missing or empty', () => {
    expect(AppError.fromProblem(500, {}).slug).toBe('error')
    expect(AppError.fromProblem(500, { type: '' }).slug).toBe('error')
  })

  it('falls back to "HTTP <status>" when title is absent', () => {
    const error = AppError.fromProblem(503, { type: '/problems/unavailable' })
    expect(error.message).toBe('HTTP 503')
  })

  it('passes detail through and leaves it undefined when absent or non-string', () => {
    expect(AppError.fromProblem(400, { detail: 'amount too large' }).detail).toBe(
      'amount too large',
    )
    expect(AppError.fromProblem(400, {}).detail).toBeUndefined()
    expect(AppError.fromProblem(400, { detail: 42 }).detail).toBeUndefined()
  })

  it('exposes per-field errors when provided and defaults to an empty array', () => {
    const withFields = AppError.fromProblem(422, {
      errors: [{ field: 'email', code: 'required' }],
    })
    expect(withFields.fieldErrors).toEqual([{ field: 'email', code: 'required' }])
    expect(AppError.fromProblem(422, {}).fieldErrors).toEqual([])
    // A non-array `errors` payload must not become fieldErrors.
    expect(AppError.fromProblem(422, { errors: 'nope' }).fieldErrors).toEqual([])
  })

  it('defends against non-object bodies', () => {
    for (const body of [null, undefined, 'boom', 123]) {
      const error = AppError.fromProblem(500, body)
      expect(error.slug).toBe('error')
      expect(error.status).toBe(500)
      expect(error.fieldErrors).toEqual([])
    }
  })
})

describe('AppError.transport', () => {
  it('builds a status-0 network error', () => {
    const error = AppError.transport('Failed to fetch')
    expect(error.status).toBe(0)
    expect(error.slug).toBe('network-error')
    expect(error.message).toBe('Failed to fetch')
    expect(error.isRetryable).toBe(false)
  })
})

describe('AppError status predicates', () => {
  it('treats 5xx, 408 and 429 as retryable', () => {
    for (const status of [500, 502, 503, 408, 429]) {
      expect(new AppError({ status, slug: 's', title: 't' }).isRetryable).toBe(true)
    }
  })

  it('treats ordinary 4xx as non-retryable', () => {
    for (const status of [400, 401, 403, 404, 422]) {
      expect(new AppError({ status, slug: 's', title: 't' }).isRetryable).toBe(false)
    }
  })

  it('flags 401 as unauthorized only', () => {
    const error = new AppError({ status: 401, slug: 's', title: 't' })
    expect(error.isUnauthorized).toBe(true)
    expect(error.isForbidden).toBe(false)
  })

  it('flags 403 as forbidden only', () => {
    const error = new AppError({ status: 403, slug: 's', title: 't' })
    expect(error.isForbidden).toBe(true)
    expect(error.isUnauthorized).toBe(false)
  })
})
