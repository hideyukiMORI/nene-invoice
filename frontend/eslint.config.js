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
//   testing-library → C3-2 で drain
//   no-restricted-syntax（i18n ハードコード）→ C3-3 で drain
//   no-restricted-imports（@/shared/ui 集約バレル）→ C3-4（構造是正・判例23）
// ---------------------------------------------------------------------------

/** testing-library 系の既存違反（18 ファイル・218 件）— C3-2 で drain。 */
const testingLibraryLedger = [
  'src/features/create-recurring-invoice/ui/CreateRecurringInvoiceForm.test.tsx',
  'src/features/list-audit-logs/model/use-list-audit-logs.test.ts',
  'src/features/list-clients/model/use-list-clients.test.ts',
  'src/features/list-invoices/model/use-list-invoices.test.ts',
  'src/features/list-items/model/use-list-items.test.ts',
  'src/features/list-quotes/model/use-list-quotes.test.ts',
  'src/features/sign-in/ui/SignInForm.test.tsx',
  'src/features/view-invoice/model/use-generate-download-link.test.ts',
  'src/features/view-invoice/ui/ViewInvoice.test.tsx',
  'src/shared/keyboard/KeyboardShortcuts.test.tsx',
  'src/shared/keyboard/use-line-grid-enter.test.tsx',
  'src/shared/ui/components/ActionError.test.tsx',
  'src/shared/ui/components/ClientCombobox.test.tsx',
  'src/shared/ui/components/DatePicker.test.tsx',
  'src/shared/ui/components/InlineAlert.test.tsx',
  'src/shared/ui/components/LineItemSuggestInput.test.tsx',
  'src/shared/ui/toast/toast.test.tsx',
  'tests/setup/vitest.setup.ts',
]

/** i18n ハードコード等 no-restricted-syntax の既存違反（7 ファイル・188 件）— C3-3 で drain。 */
const restrictedSyntaxLedger = [
  'src/features/view-dashboard/ui/ViewDashboard.tsx',
  'src/features/view-invoice/ui/ViewInvoice.tsx',
  'src/pages/help/help-content.ts',
  'src/shared/keyboard/shortcuts-data.ts',
  'src/shared/lib/format-date.ts',
  'src/shared/lib/format-money.ts',
  'src/shared/ui/components/DatePicker.tsx',
]

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

/** style prop のキー制約の既存違反（5 ファイル・10 件）— C3-2 系の小粒 drain 対象。 */
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

/** jsx-a11y の既存違反（5 ファイル・7 件）— C3-2 系の小粒 drain 対象。 */
const a11yLedger = [
  'src/features/reconcile-bank/ui/BankImportPanel.tsx',
  'src/shared/keyboard/CommandPalette.tsx',
  'src/shared/ui/components/ClientCombobox.tsx',
  'src/shared/ui/components/CsvImportPanel.tsx',
  'src/shared/ui/components/LineItemSuggestInput.tsx',
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
    files: testingLibraryLedger,
    rules: {
      'testing-library/no-container': 'off',
      'testing-library/no-manual-cleanup': 'off',
      'testing-library/no-node-access': 'off',
      'testing-library/no-wait-for-multiple-assertions': 'off',
      'testing-library/prefer-presence-queries': 'off',
      'testing-library/prefer-screen-queries': 'off',
      'testing-library/render-result-naming-convention': 'off',
    },
  },
  {
    files: restrictedSyntaxLedger,
    rules: { 'no-restricted-syntax': 'off' },
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
  {
    files: a11yLedger,
    rules: {
      'jsx-a11y/no-noninteractive-element-interactions': 'off',
      'jsx-a11y/no-noninteractive-element-to-interactive-role': 'off',
      'jsx-a11y/no-static-element-interactions': 'off',
    },
  },
  ...e2eConfig,
  ...storybook.configs['flat/recommended'],
  eslintConfigPrettier,
)
