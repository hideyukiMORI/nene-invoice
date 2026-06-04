/**
 * Cheat-sheet data for the `?` overlay (Issue #257).
 *
 * The overlay always shows ja主 + en副 simultaneously (per the spec), so the
 * labels live here as fixed ja/en pairs rather than in the locale catalog —
 * they are not locale-switched. Physical keys are universal; only labels read.
 *
 * `MOD` is the platform chord key — rendered ⌘ on mac, Ctrl elsewhere.
 */

export const MOD = 'mod'

/** A key combo: caps joined by a sequence arrow, a plus, or just spacing. */
export interface ShortcutCombo {
  caps: string[]
  join: 'then' | 'plus' | 'none'
}

export interface ShortcutRow {
  ja: string
  en: string
  combos: ShortcutCombo[]
}

export interface ShortcutGroup {
  ja: string
  en: string
  rows: ShortcutRow[]
}

export const SHORTCUT_GROUPS: ShortcutGroup[] = [
  {
    ja: '画面遷移',
    en: 'Navigation',
    rows: [
      { ja: 'ダッシュボード', en: 'Dashboard', combos: [{ caps: ['g', 'd'], join: 'then' }] },
      { ja: '見積書', en: 'Quotes', combos: [{ caps: ['g', 'q'], join: 'then' }] },
      { ja: '請求書', en: 'Invoices', combos: [{ caps: ['g', 'i'], join: 'then' }] },
      { ja: '取引先', en: 'Clients', combos: [{ caps: ['g', 'c'], join: 'then' }] },
      { ja: 'ユーザー', en: 'Users', combos: [{ caps: ['g', 'u'], join: 'then' }] },
      { ja: '会社設定', en: 'Settings', combos: [{ caps: ['g', 's'], join: 'then' }] },
      { ja: '監査ログ', en: 'Audit log', combos: [{ caps: ['g', 'a'], join: 'then' }] },
    ],
  },
  {
    ja: 'アクション',
    en: 'Actions',
    rows: [
      { ja: '新規作成', en: 'New', combos: [{ caps: ['n'], join: 'none' }] },
      { ja: '検索にフォーカス', en: 'Focus search', combos: [{ caps: ['/'], join: 'none' }] },
    ],
  },
  {
    ja: 'フォーム',
    en: 'Form',
    rows: [
      { ja: '確定 / 送信', en: 'Submit', combos: [{ caps: [MOD, 'Enter'], join: 'plus' }] },
      { ja: '中断 / 閉じる', en: 'Cancel', combos: [{ caps: ['Esc'], join: 'none' }] },
    ],
  },
  {
    ja: '全般',
    en: 'General',
    rows: [
      { ja: 'ショートカット一覧', en: 'Show shortcuts', combos: [{ caps: ['?'], join: 'none' }] },
    ],
  },
]
