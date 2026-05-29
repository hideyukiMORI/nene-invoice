/**
 * Format integer cents for display. For JPY the smallest unit equals ¥1, so cents
 * map 1:1 to yen. Formatting happens only at the UI edge — never compute on this.
 */
export function formatYen(cents: number): string {
  return `¥${cents.toLocaleString('ja-JP')}`
}
