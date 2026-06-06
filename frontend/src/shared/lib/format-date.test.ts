import { describe, expect, it } from 'vitest'
import { formatCalendarDate, formatJstDate, formatJstDateTime } from './format-date'

describe('formatJstDate', () => {
  it('converts a UTC instant to the JST calendar date (+9h)', () => {
    // 2026-06-06 21:00 UTC → 2026-06-07 06:00 JST (date rolls forward).
    expect(formatJstDate('2026-06-06 21:00:00')).toBe('2026-06-07')
    // 2026-06-06 12:33 UTC → 2026-06-06 21:33 JST (same day).
    expect(formatJstDate('2026-06-06 12:33:33')).toBe('2026-06-06')
  })

  it('honours an explicit zone suffix', () => {
    expect(formatJstDate('2026-06-06T21:00:00Z')).toBe('2026-06-07')
  })

  it('returns the input unchanged when unparseable', () => {
    expect(formatJstDate('not-a-date')).toBe('not-a-date')
  })
})

describe('formatJstDateTime', () => {
  it('converts a UTC instant to JST date and time', () => {
    expect(formatJstDateTime('2026-06-06 12:33:33')).toBe('2026-06-06 21:33')
  })
})

describe('formatCalendarDate', () => {
  it('trims to the date portion without any timezone shift', () => {
    expect(formatCalendarDate('2026-07-31')).toBe('2026-07-31')
    expect(formatCalendarDate('2026-07-31 00:00:00')).toBe('2026-07-31')
  })
})
