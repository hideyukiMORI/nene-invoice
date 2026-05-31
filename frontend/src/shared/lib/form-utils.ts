/** Converts an empty string to null; passes through non-empty strings. */
export function emptyToNull(value: string): string | null {
  return value === '' ? null : value
}
