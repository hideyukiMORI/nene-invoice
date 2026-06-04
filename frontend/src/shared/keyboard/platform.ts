/** Platform detection for shortcut display (Issue #257). Key *handling* always
 *  accepts `metaKey || ctrlKey`; only the *label* differs (⌘ on mac, Ctrl else). */
export function isMacPlatform(): boolean {
  if (typeof navigator === 'undefined') return false
  // navigator.platform is deprecated; userAgent is sufficient for ⌘-vs-Ctrl.
  return /mac|iphone|ipad|ipod/i.test(navigator.userAgent)
}
