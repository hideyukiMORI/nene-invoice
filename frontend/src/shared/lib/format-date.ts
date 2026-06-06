/**
 * Date formatting at the UI edge.
 *
 * The API returns **instant** fields (issued_at, paid_at, created_at, updated_at)
 * as UTC timestamps in `YYYY-MM-DD HH:MM:SS` form (no zone suffix; UTC by
 * convention — see backend ADR 0010). These must be converted to JST for display.
 *
 * **Calendar-date** fields (due_at, valid_until) are already JST calendar dates
 * and must NOT be timezone-shifted — only trimmed to the date portion.
 */

const JST = 'Asia/Tokyo'

/** Parses a backend instant string (UTC, with or without an explicit zone) to a Date. */
function parseInstant(value: string): Date {
  const trimmed = value.trim()
  const hasZone = /([zZ]|[+-]\d\d:?\d\d)$/.test(trimmed)
  const iso = trimmed.includes('T') ? trimmed : trimmed.replace(' ', 'T')

  return new Date(hasZone ? iso : `${iso}Z`)
}

/** A UTC instant as the JST calendar date, `YYYY-MM-DD`. */
export function formatJstDate(value: string): string {
  const date = parseInstant(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('sv-SE', { timeZone: JST }).format(date)
}

/** A UTC instant as JST date and time, `YYYY-MM-DD HH:MM`. */
export function formatJstDateTime(value: string): string {
  const date = parseInstant(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('sv-SE', {
    timeZone: JST,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

/**
 * A calendar-date field (already JST) trimmed to `YYYY-MM-DD`. No timezone shift —
 * the stored value is the intended Japan calendar date.
 */
export function formatCalendarDate(value: string): string {
  return value.slice(0, 10)
}
