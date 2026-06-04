/**
 * Inline keycap hint (Issue #257 §04 / design 03 convention). A small, decorative
 * `.kbd` shown next to the few most-frequent affordances only — search (`/`) and
 * new (`n`). The control carries `aria-keyshortcuts`; this keycap is `aria-hidden`
 * and hides on touch devices.
 *
 * Variant adapts contrast to the surface (design 03 §02):
 * - `solid` — on a filled/accent button (translucent white).
 * - `ghost` — on a plain/muted surface (default).
 * - `outline` — on the deep-green sidebar (border only).
 */
export type KbdHintVariant = 'solid' | 'ghost' | 'outline'

export function KbdHint({
  children,
  variant = 'ghost',
}: {
  children: string
  variant?: KbdHintVariant
}) {
  return (
    <kbd className={`kbd kbd-hint ${variant}`} aria-hidden="true">
      {children}
    </kbd>
  )
}
