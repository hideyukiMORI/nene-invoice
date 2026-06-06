import { useEffect, useState } from 'react'

/**
 * Returns `value` delayed by `delayMs` — updates only after the input has been
 * stable for that long. Used to throttle typeahead queries before they hit the
 * server (e.g. the client combobox search, #328).
 */
export function useDebouncedValue<T>(value: T, delayMs = 250): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const handle = setTimeout(() => {
      setDebounced(value)
    }, delayMs)

    return () => {
      clearTimeout(handle)
    }
  }, [value, delayMs])

  return debounced
}
