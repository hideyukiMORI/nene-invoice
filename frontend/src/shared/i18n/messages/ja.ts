/**
 * Authoritative message catalog (primary locale = ja, ADR 0005). Add new keys
 * here first; en.ts is a Partial that falls back to these at runtime.
 */
export const jaMessages = {
  'common.appName': 'NeNe Invoice 管理',
  'common.actions.retry': '再試行',
  'common.actions.signOut': 'ログアウト',
  'common.error.generic': 'エラーが発生しました。',
  'common.locale.ja': '日本語',
  'common.locale.en': 'English',
  'admin.auth.title': 'ログイン',
  'admin.auth.email': 'メールアドレス',
  'admin.auth.password': 'パスワード',
  'admin.auth.submit': 'ログイン',
  'admin.auth.failed': 'メールアドレスまたはパスワードが正しくありません。',
  'admin.auth.emailInvalid': '有効なメールアドレスを入力してください。',
  'admin.auth.passwordRequired': 'パスワードを入力してください。',
  'admin.account.signedInAs': '{{email}} でログイン中',
  'admin.nav.invoices': '請求書',
  'admin.invoices.title': '請求書一覧',
  'admin.invoices.loading': '読み込み中…',
  'admin.invoices.empty': '請求書がまだありません。',
  'admin.invoices.error': '請求書を取得できませんでした。',
  'admin.invoices.col.number': '番号',
  'admin.invoices.col.status': '状態',
  'admin.invoices.col.client': '取引先 ID',
  'admin.invoices.col.total': '合計',
  'admin.invoices.status.draft': '下書き',
  'admin.invoices.status.issued': '発行済み',
  'admin.invoices.status.partially_paid': '一部入金',
  'admin.invoices.status.paid': '入金済み',
  'admin.forbidden.title': 'アクセス権限がありません。',
} as const
