import { describe, expect, it } from 'vitest'
import { formatTaxRate, formatYen } from './format-money'

describe('formatYen', () => {
  it('formats integer cents 1:1 to yen with thousands separators', () => {
    expect(formatYen(116480)).toBe('¥116,480')
    expect(formatYen(1000000)).toBe('¥1,000,000')
  })

  it('handles zero', () => {
    expect(formatYen(0)).toBe('¥0')
  })
})

describe('formatTaxRate', () => {
  it('converts basis points to a percent label', () => {
    expect(formatTaxRate(1000)).toBe('10%')
    expect(formatTaxRate(800)).toBe('8%')
    expect(formatTaxRate(0)).toBe('0%')
  })
})
