import { useEffect, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { SHOW_SHORTCUTS_EVENT } from './overlay-control'
import { ShortcutsOverlay } from './ShortcutsOverlay'
import { KBD_LIST_EVENT, type RowCursorAction } from './use-row-cursor'

function emitListAction(action: RowCursorAction): void {
  document.dispatchEvent(new CustomEvent(KBD_LIST_EVENT, { detail: { action } }))
}

/** g-prefix sequence timeout (spec §02-D). */
const G_TIMEOUT_MS = 1200

/** Layer 1 — g→key navigation targets. */
const GOTO: Record<string, string> = {
  d: '/dashboard',
  q: '/quotes',
  i: '/invoices',
  c: '/clients',
  m: '/items',
  t: '/templates',
  u: '/users',
  s: '/settings',
  a: '/audit-logs',
}

/** Layer 2 — `n` (new) resolves by the current list route (spec §06). */
const NEW_ROUTE: Record<string, string> = {
  '/quotes': '/quotes/new',
  '/invoices': '/invoices/new',
  '/clients': '/clients/new',
  '/items': '/items/new',
  '/templates': '/templates/new',
  '/users': '/users/new',
}

/** Parent list roots for `u` (back to list). */
const LIST_ROOTS = [
  '/invoices',
  '/quotes',
  '/clients',
  '/items',
  '/templates',
  '/users',
  '/audit-logs',
]

function parentListOf(path: string): string | null {
  return LIST_ROOTS.find((root) => path.startsWith(`${root}/`)) ?? null
}

/** True when focus sits in an editable control — single keys must not fire. */
function isEditableTarget(target: EventTarget | null): boolean {
  return (
    target instanceof HTMLElement &&
    (target.isContentEditable || target.matches('input, textarea, select'))
  )
}

/**
 * Global keyboard dispatcher (Issue #257, spec §02 contract A→E). Mounted once
 * inside the authenticated shell — never on the login screen. Wires navigation
 * (g-prefix), the ⌘/Ctrl+Enter submit chord, the `?` overlay, single-key actions
 * (n / /), and the list row cursor (j / k / o / Enter) via the `kbd:list` event.
 * The line-item grid Enter is handled form-locally, not here.
 */
export function KeyboardShortcuts() {
  const navigate = useNavigate()
  const location = useLocation()
  const [overlayOpen, setOverlayOpen] = useState(false)
  const [pendingG, setPendingG] = useState(false)

  // Refs mirror state so the document listener (attached once) reads fresh
  // values without re-binding on every keystroke.
  const overlayRef = useRef(false)
  const pendingRef = useRef(false)
  const pathRef = useRef(location.pathname)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    overlayRef.current = overlayOpen
  }, [overlayOpen])
  // The sidebar "shortcuts" button opens the cheat-sheet via this event.
  useEffect(() => {
    const open = (): void => {
      setOverlayOpen(true)
    }
    document.addEventListener(SHOW_SHORTCUTS_EVENT, open)
    return () => {
      document.removeEventListener(SHOW_SHORTCUTS_EVENT, open)
    }
  }, [])
  useEffect(() => {
    pendingRef.current = pendingG
  }, [pendingG])
  useEffect(() => {
    pathRef.current = location.pathname
  }, [location.pathname])

  useEffect(() => {
    const clearPending = (): void => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current)
        timerRef.current = null
      }
      pendingRef.current = false
      setPendingG(false)
    }

    const onKeydown = (e: KeyboardEvent): void => {
      // A — submit chord & Esc are always handled, even inside fields / IME.
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        const form = e.target instanceof HTMLElement ? e.target.closest('form') : null
        if (form !== null) {
          e.preventDefault()
          form.requestSubmit()
        }
        return
      }
      if (e.key === 'Escape') {
        if (overlayRef.current) setOverlayOpen(false)
        else if (pendingRef.current) clearPending()
        return
      }

      // B — IME / editable-field guard. keyCode 229 is the legacy IME signal
      // some browsers emit when isComposing is still false on the first keydown;
      // it is required for robust Japanese-input safety (spec §02-B).
      // eslint-disable-next-line @typescript-eslint/no-deprecated
      if (e.isComposing || e.keyCode === 229) return
      if (isEditableTarget(e.target)) return

      // C — modifier+single-key belongs to the OS/browser, not us.
      if (e.metaKey || e.ctrlKey || e.altKey) return

      // `?` (Shift+/) toggles the cheat-sheet from anywhere outside a field.
      if (e.key === '?') {
        e.preventDefault()
        clearPending()
        setOverlayOpen((open) => !open)
        return
      }

      // While the overlay is open it owns the keyboard (besides Esc / ?).
      if (overlayRef.current) return

      // D — g-prefix sequence.
      if (pendingRef.current) {
        const dest = GOTO[e.key]
        clearPending()
        if (dest !== undefined) {
          e.preventDefault()
          void navigate(dest)
        }
        return
      }
      if (e.key === 'g') {
        e.preventDefault()
        pendingRef.current = true
        setPendingG(true)
        if (timerRef.current !== null) clearTimeout(timerRef.current)
        timerRef.current = setTimeout(clearPending, G_TIMEOUT_MS)
        return
      }

      // Layer 2 — focus the search box, or the first form field (spec §08).
      if (e.key === '/') {
        const target =
          document.querySelector('[data-kbd="search"]') ??
          document.querySelector(
            'form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), form textarea, form select',
          )
        if (target instanceof HTMLElement) {
          e.preventDefault()
          target.focus()
        }
        return
      }

      // Layer 2 — context-sensitive new (`n`), resolved by the current route.
      if (e.key === 'n') {
        const dest = NEW_ROUTE[pathRef.current]
        if (dest !== undefined) {
          e.preventDefault()
          void navigate(dest)
        }
        return
      }

      // Layer 2 — back to the parent list (`u`), Gmail-style.
      if (e.key === 'u') {
        const list = parentListOf(pathRef.current)
        if (list !== null) {
          e.preventDefault()
          void navigate(list)
        }
        return
      }

      // Layer 4 — list row cursor. A mounted list (useRowCursor) consumes these;
      // on other screens they are no-ops.
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault()
        emitListAction(e.key === 'j' ? 'down' : 'up')
        return
      }
      if (e.key === 'o') {
        emitListAction('open')
        return
      }
      // Enter opens the cursored row only when nothing interactive is focused —
      // otherwise it must activate the focused control (button/link) normally.
      if (e.key === 'Enter' && (e.target === document.body || e.target === null)) {
        emitListAction('open')
      }
    }

    document.addEventListener('keydown', onKeydown)
    return () => {
      document.removeEventListener('keydown', onKeydown)
      if (timerRef.current !== null) clearTimeout(timerRef.current)
    }
  }, [navigate])

  return (
    <>
      {pendingG && (
        <div className="kbd-gind" role="status" aria-live="polite">
          g…
        </div>
      )}
      {overlayOpen && (
        <ShortcutsOverlay
          onClose={() => {
            setOverlayOpen(false)
          }}
        />
      )}
    </>
  )
}
