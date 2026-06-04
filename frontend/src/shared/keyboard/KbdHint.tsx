/**
 * Inline keycap hint (Issue #257, spec §04). A small, muted `.kbd` shown next
 * to the two most-frequent affordances only — search (`/`) and new (`n`). It is
 * decorative (`aria-hidden`) and hides on touch devices via `.kbd-hint`.
 */
export function KbdHint({ children }: { children: string }) {
  return (
    <kbd className="kbd kbd-hint" aria-hidden="true">
      {children}
    </kbd>
  )
}
