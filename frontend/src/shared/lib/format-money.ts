/**
 * Format integer cents for display. For JPY the smallest unit equals ¥1, so cents
 * map 1:1 to yen. Formatting happens only at the UI edge — never compute on this.
 * A negative amount (a discount line, #787) reads "−¥100,000" with U+2212, as on the PDF.
 */
export function formatYen(cents: number): string {
  const sign = cents < 0 ? '−' : ''
  return `${sign}¥${Math.abs(cents).toLocaleString('ja-JP')}`
}

/** Basis points → percent label (1000 → "10%", 800 → "8%"). */
export function formatTaxRate(bps: number): string {
  return `${(bps / 100).toString()}%`
}
