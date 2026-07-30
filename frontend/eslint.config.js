import nene2 from '@hideyukimori/nene2-standards'
import eslintConfigPrettier from 'eslint-config-prettier'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import storybook from 'eslint-plugin-storybook'
import globals from 'globals'
import { e2eConfig } from './eslint.e2e.config.js'
import tseslint from 'typescript-eslint'

// ---------------------------------------------------------------------------
// 判例26 台帳 — 合成形の導入時点で既に違反しているファイルの列挙。
//
// 「既存は列挙して off・新規は full 強制」。列挙に無いファイルは初日から落ちる。
// ここに足すのは移行のためだけで、**drain 計画つきでしか増やさない**（#739）。
//   no-restricted-syntax（Intl 直呼び・format 経由 MUST）→ C4b（共有 format 供給待ち）
//   no-restricted-imports（@/shared/ui 集約バレル）→ C3-4（構造是正・判例23）
// ---------------------------------------------------------------------------

/**
 * no-restricted-syntax の残存（2 ファイル・3 件）— **C4b へ移管**（hub 裁定 2026-07-30）。
 * `format-date.ts:28,36` の Intl 直呼びと `format-money.ts:6` の `toLocaleString` は、
 * 自前実装をやめて `@hideyukimori/nene2-i18n/format` へ寄せる話なので、C4b の
 * 「i18n runtime 供給待ち」の束と同じ。C3-3（#746）の射程からは外した。
 *
 * C3-3 で drain 済み（#746）: ViewDashboard / ViewInvoice / DatePicker の計 12 件は
 * ロケールカタログへ移した。help-content / shortcuts-data の 174 件は下の公認差異へ。
 */
const restrictedSyntaxLedger = ['src/shared/lib/format-date.ts', 'src/shared/lib/format-money.ts']

/** `@/shared/ui` 集約バレル import の既存違反（60 ファイル・各1件）— C3-4 の構造是正で消える。 */
const sharedUiBarrelLedger = [
  'src/app/auth-gate.tsx',
  'src/app/home-redirect.tsx',
  'src/app/providers.tsx',
  'src/app/require-role.tsx',
  'src/app/root-error-boundary.tsx',
  'src/entities/bank-transaction/status-tone.ts',
  'src/entities/invoice/status-tone.ts',
  'src/entities/quote/status-tone.ts',
  'src/features/create-client/ui/CreateClientForm.tsx',
  'src/features/create-invoice/model/use-create-invoice.ts',
  'src/features/create-invoice/ui/CreateInvoiceForm.tsx',
  'src/features/create-item/ui/CreateItemForm.tsx',
  'src/features/create-organization/ui/CreateOrganizationForm.tsx',
  'src/features/create-quote/model/use-create-quote.ts',
  'src/features/create-quote/ui/CreateQuoteForm.tsx',
  'src/features/create-recurring-invoice/model/use-create-recurring-invoice.ts',
  'src/features/create-recurring-invoice/ui/CreateRecurringInvoiceForm.tsx',
  'src/features/create-user/ui/CreateUserForm.tsx',
  'src/features/edit-client/ui/EditClient.tsx',
  'src/features/edit-company-settings/ui/EditCompanySettings.tsx',
  'src/features/edit-item/ui/EditItem.tsx',
  'src/features/edit-recurring-invoice/model/use-edit-recurring-invoice.ts',
  'src/features/edit-recurring-invoice/ui/EditRecurringInvoice.tsx',
  'src/features/edit-user/ui/EditUser.tsx',
  'src/features/gateway-settings/ui/GatewaySettings.tsx',
  'src/features/import-clients/ui/ImportClients.tsx',
  'src/features/import-items/ui/ImportItems.tsx',
  'src/features/issue-invoice/model/use-issue-invoice.ts',
  'src/features/issue-invoice/ui/IssueInvoice.tsx',
  'src/features/list-audit-logs/ui/ListAuditLogs.tsx',
  'src/features/list-clients/ui/ListClients.tsx',
  'src/features/list-invoices/ui/ListInvoices.tsx',
  'src/features/list-items/ui/ListItems.tsx',
  'src/features/list-organizations/ui/ListOrganizations.tsx',
  'src/features/list-quotes/ui/ListQuotes.tsx',
  'src/features/list-recurring-invoices/ui/ListRecurringInvoices.tsx',
  'src/features/list-templates/ui/ListTemplates.tsx',
  'src/features/list-users/ui/ListUsers.tsx',
  'src/features/manage-company-seal/ui/ManageCompanySeal.tsx',
  'src/features/manage-payments/model/use-manage-payments.ts',
  'src/features/manage-payments/ui/ManagePayments.tsx',
  'src/features/manage-service-tokens/ui/ManageServiceTokens.tsx',
  'src/features/reconcile-bank/ui/BankImportPanel.tsx',
  'src/features/reconcile-bank/ui/BankWorkbench.tsx',
  'src/features/reconcile-bank/ui/ReconcileBank.tsx',
  'src/features/sign-in/ui/SignInForm.tsx',
  'src/features/template-bar/model/use-template-bar.ts',
  'src/features/template-bar/ui/TemplateBar.tsx',
  'src/features/template-form/ui/TemplateForm.tsx',
  'src/features/view-dashboard/ui/ViewDashboard.tsx',
  'src/features/view-invoice/ui/EmailPreviewModal.tsx',
  'src/features/view-invoice/ui/ViewInvoice.tsx',
  'src/features/view-quote/ui/ViewQuote.tsx',
  'src/pages/company-settings/CompanySettingsPage.tsx',
  'src/pages/invoice-detail/InvoiceDetailPage.tsx',
  'src/shared/ui/components/CsvImportPanel.tsx',
  'src/shared/ui/components/DatePicker.tsx',
  'src/shared/ui/components/FilterBar.tsx',
  'src/shared/ui/components/LineItemsTable.tsx',
  'src/shared/ui/toast/ToastProvider.tsx',
]

/**
 * style prop のキー制約の既存違反（5 ファイル・10 件）— **W3 同乗**（hub 裁定 2026-07-30）。
 * CSS 変数注入への書き換えなので、意匠再生成と同じファイル群を二度触らない。
 * （旧記載の「C3-2 系」は C3-2 = テスト専用 drain と確定したため振り直した。a11y 分は
 * C3-2b #744 で drain 済み。）
 */
const stylePropLedger = [
  'src/features/edit-company-settings/ui/EditCompanySettings.tsx',
  'src/features/manage-company-seal/ui/ManageCompanySeal.tsx',
  'src/features/view-dashboard/ui/ViewDashboard.tsx',
  'src/features/view-invoice/ui/ViewInvoice.tsx',
  'src/features/view-quote/ui/ViewQuote.tsx',
]

/** 未知クラス（z-modal / field-inline 等）の既存違反（7 ファイル・8 件）— W3 の独自クラス drain と同じ根。 */
const unknownClassLedger = [
  'src/features/reconcile-bank/ui/BankWorkbench.tsx',
  'src/features/template-bar/ui/TemplateBar.tsx',
  'src/features/view-dashboard/ui/ViewDashboard.tsx',
  'src/features/view-invoice/ui/EmailPreviewModal.tsx',
  'src/pages/layout/AppShell.tsx',
  'src/shared/ui/components/ConfirmDialog.tsx',
  'src/shared/ui/components/FilterBar.tsx',
]

export default tseslint.config(
  {
    ignores: [
      'dist',
      'storybook-static',
      'node_modules',
      'coverage',
      '.vite',
      'src/shared/api/schema.gen.ts',
      'public/mockServiceWorker.js',
      // Build/config files live outside tsconfig; base enables the typed
      // projectService, which errors on files it cannot place in a project.
      '*.config.{ts,js,mjs}',
      'tools/**',
      '.storybook/**',
      '**/*.mjs',
    ],
  },
  // base enables the typed projectService (auto-discovers tsconfig), so we only
  // supply browser globals here — no explicit parserOptions.project.
  {
    files: ['src/**/*.{ts,tsx}', 'tests/**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
    },
  },
  // 共有合成形（README canonical order・部分採用は C3 未達）。FSD 境界・transport
  // 禁止・a11y・i18n ハードコード検出・testing-library 規律はここから来る。
  ...nene2.base,
  ...nene2.fsd,
  ...nene2.api,
  ...nene2.stylingWith(),
  ...nene2.i18n,
  ...nene2.testing,
  // React hygiene はフリート形に含まれないのでリポ固有の追加として残す。
  {
    files: ['src/**/*.{ts,tsx}'],
    plugins: { 'react-hooks': reactHooks, 'react-refresh': reactRefresh },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
    },
  },
  // 公認差異（override 実行可能登録）: React Hook Form の typed field path が
  // 数値インデックスを要求する（`line_items.${index}.description`）。payout も
  // 同じ登録を持つ。severity は緩めない。
  {
    files: ['src/**/*.{ts,tsx}'],
    rules: {
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
    },
  },
  // --- 判例26 台帳の適用（既存のみ off・新規は full 強制） ---
  {
    files: restrictedSyntaxLedger,
    rules: { 'no-restricted-syntax': 'off' },
  },
  // 公認差異（台帳ではない・drain 対象外）: ja/en を併記して持つコンテンツモジュール。
  // 会議R1⑦が防ぐ「ユーザ知覚文字列が翻訳不能なまま埋まる」状態ではない — 翻訳は
  // 両方そこに在る。
  //   - pages/help/help-content.ts … 手順・フロー・FAQ の長文散文。フラットなキーに
  //     合わないので ja/en ペアで持つ設計（ファイル冒頭に明記）。HelpPage.tsx が
  //     `locale === 'en' ? b.en : b.ja` で実際に切り替える。
  //   - shared/keyboard/shortcuts-data.ts … オーバーレイは仕様上 ja主+en副を**同時**に
  //     描画する。t() は片方しか返せないので、そもそもカタログでは表現できない。
  // 解除条件: 長文散文をロケールカタログ化する方針が別途決まったとき。
  // 射程調整（{ja,en} 型リテラルの除外）は凍結明けの標準側判断 — fleet #162 に提案済み。
  {
    files: ['src/pages/help/help-content.ts', 'src/shared/keyboard/shortcuts-data.ts'],
    rules: { 'no-restricted-syntax': 'off' },
  },
  // 公認差異（台帳ではない・drain 対象外）: vitest.config.ts が `globals: false` なので
  // React Testing Library は自前の auto-cleanup を登録できない（登録条件がグローバル
  // afterEach の存在）。よって setup での手動 cleanup() は必須で、外すと DOM が
  // テスト間に漏れる（実測: 11 ファイル / 42 テストが失敗）。将来 `globals: true` へ
  // 倒すなら、この登録ごと不要になる。
  {
    files: ['tests/setup/**'],
    rules: { 'testing-library/no-manual-cleanup': 'off' },
  },
  {
    files: sharedUiBarrelLedger,
    rules: { 'no-restricted-imports': 'off' },
  },
  {
    files: stylePropLedger,
    rules: { 'nene2/style-prop-css-vars-only': 'off' },
  },
  {
    files: unknownClassLedger,
    rules: { 'better-tailwindcss/no-unknown-classes': 'off' },
  },
  ...e2eConfig,
  ...storybook.configs['flat/recommended'],
  eslintConfigPrettier,
)
