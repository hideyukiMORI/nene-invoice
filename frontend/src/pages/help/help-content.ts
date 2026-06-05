/**
 * Help-page content (design 05, Issue #310). Co-located bilingual ja/en pairs —
 * like `shared/keyboard/shortcuts-data.ts` — rather than locale-catalog keys,
 * since the page is long-form prose with steps, flows, and an FAQ that don't fit
 * flat keys. `HelpPage` picks ja or en from the active locale.
 *
 * Inline markup inside any string: `**bold**` and `` `code` `` (rendered by the
 * page's <Rich> helper). Keep wording aligned with the real UI labels (the
 * buttons / statuses a beginner actually sees), so the guide stays accurate.
 */

/** A translated string: primary ja + secondary en. May contain inline markup. */
export interface Bi {
  ja: string
  en: string
}

export type HelpBlock =
  | { kind: 'lede'; text: Bi }
  | { kind: 'subhead'; text: Bi }
  | { kind: 'steps'; items: { title: Bi; desc: Bi }[] }
  | { kind: 'proc'; items: Bi[] }
  | { kind: 'flow'; chips: Bi[]; branch?: Bi }
  | { kind: 'terms'; items: { term: Bi; desc: Bi }[] }
  | { kind: 'deflist'; items: { term: Bi; desc: Bi }[] }
  | { kind: 'note'; tone?: 'warn'; title?: Bi; text: Bi }
  | { kind: 'options'; items: { title: Bi; desc: Bi }[] }
  | { kind: 'keys'; groups: { heading: Bi; rows: { label: Bi; caps: string[] }[] }[] }
  | { kind: 'faq'; items: { q: Bi; a: Bi }[] }

export interface HelpSection {
  /** Anchor id (also the ToC target). */
  id: string
  title: Bi
  /** Marks the section as admin-only (badge in the ToC and the heading). */
  admin?: boolean
  blocks: HelpBlock[]
}

export const HELP_LABELS = {
  eyebrow: { ja: 'ヘルプ', en: 'Help' },
  heroTitle: { ja: '操作ガイド・よくある質問', en: 'Guides & FAQ' },
  heroLede: {
    ja: 'NeNe Invoice を初めて使う方向けの操作ガイドです。見積から請求・入金までの流れと、つまずきやすいポイントをまとめています。',
    en: 'A getting-started guide for people new to NeNe Invoice. It walks through the quote → invoice → payment flow and the spots people commonly get stuck on.',
  },
  chipQualified: { ja: '適格請求書（インボイス）対応', en: 'Qualified invoice ready' },
  chipSelfhost: { ja: '自己ホスト型', en: 'Self-hosted' },
  chipUpdated: { ja: '最終更新', en: 'Updated' },
  updatedDate: '2026-06-05',
  toc: { ja: '目次', en: 'Contents' },
  adminBadge: { ja: '管理者', en: 'Admin' },
  backToToc: { ja: '目次へ戻る', en: 'Back to contents' },
  footTitle: { ja: '解決しませんでしたか？', en: 'Still need help?' },
  footDesc: {
    ja: '管理者にお問い合わせいただくか、キーボードショートカット一覧（?）もご活用ください。',
    en: 'Contact your administrator, or press ? for the keyboard-shortcut list.',
  },
  footButton: { ja: 'サポートへ問い合わせ', en: 'Contact support' },
  faqTitle: { ja: 'よくある質問', en: 'FAQ' },
} as const

export const HELP_SECTIONS: HelpSection[] = [
  {
    id: 's1',
    title: { ja: 'はじめに（クイックスタート）', en: 'Getting started (quick start)' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: 'NeNe Invoice は、見積・請求・入金をまとめて管理する**自己ホスト型システム**です。日本の適格請求書（インボイス）に対応しています。',
          en: 'NeNe Invoice is a **self-hosted system** for managing quotes, invoices, and payments together. It supports Japan’s qualified invoice format.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '全体の流れはシンプルです。見積書を作り、承認されたら請求書に変換し、請求書を「発行」して取引先へ送付し、入金されたら記録します。ダッシュボードで未払い・売掛・入金の状況をいつでも把握できます。',
          en: 'The flow is simple: create a quote, convert it to an invoice once accepted, “issue” the invoice and send it to the client, then record the payment when it arrives. The dashboard shows your unpaid / receivable / payment situation at any time.',
        },
      },
      { kind: 'subhead', text: { ja: 'まず最初の3ステップ', en: 'The first three steps' } },
      {
        kind: 'steps',
        items: [
          {
            title: {
              ja: '会社情報を登録する（設定）',
              en: 'Register your company info (Settings)',
            },
            desc: {
              ja: '特に **登録番号（`T＋13桁`）** は請求書の発行に必須です。',
              en: 'In particular the **registration number (`T + 13 digits`)** is required to issue invoices.',
            },
          },
          {
            title: { ja: '取引先を登録する', en: 'Add your clients' },
            desc: {
              ja: '「取引先 → 取引先を作成」から登録します。',
              en: 'Add them from Clients → Create client.',
            },
          },
          {
            title: { ja: '見積書、または請求書を作成する', en: 'Create a quote, or an invoice' },
            desc: {
              ja: '見積から始めても、請求書を直接作っても構いません。',
              en: 'You can start from a quote, or create an invoice directly.',
            },
          },
        ],
      },
      {
        kind: 'note',
        title: {
          ja: 'データはあなたの環境に保存されます。',
          en: 'Your data stays in your environment.',
        },
        text: {
          ja: '自己ホスト型のため、請求情報が外部サービスへ送信されることはありません。',
          en: 'Because it is self-hosted, billing information is never sent to an external service.',
        },
      },
    ],
  },
  {
    id: 's2',
    title: { ja: '用語ミニ辞典', en: 'Mini glossary' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '画面でよく出てくる用語をかんたんに説明します。',
          en: 'Quick explanations of terms you’ll see around the app.',
        },
      },
      {
        kind: 'terms',
        items: [
          {
            term: { ja: '適格請求書（インボイス）', en: 'Qualified invoice' },
            desc: {
              ja: '登録番号や税率区分を記載した、仕入税額控除に使える請求書。NeNe Invoice の請求書は**「発行」時にこの形式で採番**されます。',
              en: 'An invoice listing the registration number and tax-rate breakdown, usable for input-tax credit. Invoices here are **numbered in this format when you “issue” them**.',
            },
          },
          {
            term: { ja: '登録番号', en: 'Registration number' },
            desc: {
              ja: '適格請求書発行事業者の番号（`T＋13桁`）。会社設定に登録します。',
              en: 'The qualified-invoice issuer number (`T + 13 digits`). Set it in company Settings.',
            },
          },
          {
            term: { ja: '締め日／支払サイト', en: 'Closing day / payment terms' },
            desc: {
              ja: '「毎月◯日締め・翌月△日払い」のような支払条件。設定しておくと請求書の**支払期限が自動計算**されます。',
              en: 'Terms like “close on the Nth, pay on the Mth of next month.” Once set, an invoice’s **due date is calculated automatically**.',
            },
          },
          {
            term: { ja: '残高（売掛）', en: 'Outstanding (receivable)' },
            desc: {
              ja: '請求済みで、まだ入金されていない金額。**合計 − 入金済み** の額です。',
              en: 'Billed but not yet received — **total − amount paid**.',
            },
          },
          {
            term: { ja: 'エイジング', en: 'Aging' },
            desc: {
              ja: '売掛を「未到来／1〜30日超過／31日以上超過」に分けた滞留状況。',
              en: 'Receivables grouped by how overdue they are: not yet due / 1–30 days / 31+ days.',
            },
          },
        ],
      },
    ],
  },
  {
    id: 's3',
    title: { ja: '初期設定', en: 'Initial setup' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '最初に会社情報・既定値・ユーザーを整えておくと、以降の操作がスムーズです。',
          en: 'Setting up your company info, defaults, and users first makes everything afterward smoother.',
        },
      },
      {
        kind: 'deflist',
        items: [
          {
            term: { ja: '会社情報（設定）', en: 'Company info (Settings)' },
            desc: {
              ja: '法人名（必須）・住所・電話・メール、振込先（銀行名／支店名／口座種別／口座番号）、そして**登録番号（`T＋13桁`）**を登録します。登録番号は請求書の発行に必須で、未登録だと発行できません。',
              en: 'Legal name (required), address, phone, email, bank details (bank / branch / account type / number), and the **registration number (`T + 13 digits`)**. The registration number is required to issue invoices; without it you cannot issue.',
            },
          },
          {
            term: { ja: '請求・見積の既定', en: 'Invoice / quote defaults' },
            desc: {
              ja: '「見積の有効期限（日数）」を入れると、見積作成時に有効期限が自動で入ります。「締め日・支払月・支払日」を設定すると、請求書の支払期限が自動計算されます（例：末日締め・翌月末払い）。空欄なら毎回手入力です。',
              en: 'Set “quote validity (days)” to auto-fill a quote’s expiry. Set “closing day / payment month / pay day” to auto-calculate an invoice’s due date (e.g. close at month-end, pay end of next month). Leave blank to enter it each time.',
            },
          },
          {
            term: { ja: 'ユーザーとロール', en: 'Users and roles' },
            desc: {
              ja: '**管理者**はユーザー管理を含む操作、**メンバー**は日常の見積・請求・入金、**閲覧者**は閲覧のみ、**スーパー管理者**は組織（テナント）全体の管理を担います。',
              en: '**Admins** manage users and operate the system, **members** handle day-to-day quotes / invoices / payments, **viewers** are read-only, and **superadmins** manage the whole organization (tenant).',
            },
          },
        ],
      },
    ],
  },
  {
    id: 's4',
    title: { ja: '取引先', en: 'Clients' },
    blocks: [
      {
        kind: 'proc',
        items: [
          {
            ja: '「取引先」→「**取引先を作成**」を開きます。',
            en: 'Open Clients → **Create client**.',
          },
          {
            ja: '名称（必須）・担当者・メール・登録番号などを入力して保存します。',
            en: 'Enter name (required), contact, email, registration number, etc., then save.',
          },
          { ja: '編集は一覧の行から行います。', en: 'Edit a client from its row in the list.' },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: '取引先のメールを登録しておくと、請求書の**「メールで送信」**が使えます。',
          en: 'Registering the client’s email lets you use **“Send by email”** on an invoice.',
        },
      },
    ],
  },
  {
    id: 's5',
    title: { ja: '見積書', en: 'Quotes' },
    blocks: [
      {
        kind: 'proc',
        items: [
          {
            ja: '「見積書」→「**見積書を作成**」を開きます。',
            en: 'Open Quotes → **Create quote**.',
          },
          {
            ja: '取引先を選び、品目・数量・単価・税率を入力します。行は必要なだけ追加できます。',
            en: 'Pick a client and enter line items (item, quantity, unit price, tax rate). Add as many rows as you need.',
          },
          {
            ja: '有効期限を設定して保存すると「**下書き**」になります。',
            en: 'Set the expiry and save — it becomes a **Draft**.',
          },
        ],
      },
      { kind: 'subhead', text: { ja: '状態の流れ', en: 'Status flow' } },
      {
        kind: 'flow',
        chips: [
          { ja: '下書き', en: 'Draft' },
          { ja: '送付済み', en: 'Sent' },
          { ja: '承認済み', en: 'Accepted' },
        ],
        branch: { ja: '／ 却下 ／ 期限切れ', en: '/ Rejected / Expired' },
      },
      {
        kind: 'lede',
        text: {
          ja: '詳細画面の操作ボタンで進めます。PDF をダウンロードして送付できます。',
          en: 'Move through these with the action buttons on the detail screen. You can download a PDF to send.',
        },
      },
      {
        kind: 'note',
        title: {
          ja: '承認済みの見積は「請求書に変換する」が使えます。',
          en: 'An accepted quote can use “Convert to invoice.”',
        },
        text: {
          ja: '内容（取引先・明細・金額）を引き継いだ請求書の下書きが作成されます。',
          en: 'It creates an invoice draft carrying over the client, line items, and amounts.',
        },
      },
    ],
  },
  {
    id: 's6',
    title: { ja: '請求書', en: 'Invoices' },
    blocks: [
      {
        kind: 'proc',
        items: [
          {
            ja: '「請求書」→「**請求書を作成**」、または承認済み見積から変換します。最初は「下書き（未採番）」です。',
            en: 'Open Invoices → **Create invoice**, or convert from an accepted quote. It starts as a “Draft (not yet numbered).”',
          },
          {
            ja: '内容を確認し「**発行する（適格請求書）**」を押します。確認後に請求番号が採番され「発行済み」になります。',
            en: 'Review it and press **“Issue (qualified invoice).”** After confirmation it gets an invoice number and becomes “Issued.”',
          },
          {
            ja: '発行後は内容が確定します（編集できません）。**下書きのうちに必ず確認**してください。',
            en: 'Once issued, the contents are final (no longer editable). **Always check while it is still a draft.**',
          },
        ],
      },
      {
        kind: 'note',
        tone: 'warn',
        title: { ja: '発行には登録番号が必須です', en: 'Issuing requires a registration number' },
        text: {
          ja: '未登録だと「発行できませんでした。会社情報の登録番号をご確認ください」と表示されます。設定で `T＋13桁` の登録番号を保存してください。',
          en: 'Without it you’ll see “Could not issue — please check the registration number.” Save the `T + 13-digit` number in Settings.',
        },
      },
      { kind: 'subhead', text: { ja: '送付方法は3つ', en: 'Three ways to deliver it' } },
      {
        kind: 'options',
        items: [
          {
            title: { ja: 'PDF をダウンロード', en: 'Download a PDF' },
            desc: { ja: '手元に保存して送付できます。', en: 'Save it and send it yourself.' },
          },
          {
            title: { ja: 'メールで送信', en: 'Send by email' },
            desc: {
              ja: '取引先メールへ直接送信します。',
              en: 'Send straight to the client’s email.',
            },
          },
          {
            title: { ja: 'クライアント向けリンク', en: 'Client link' },
            desc: {
              ja: '期限つきの閲覧／DL用URL。ログイン不要で相手が開けます。',
              en: 'A time-limited view/download URL the recipient opens without logging in.',
            },
          },
        ],
      },
      {
        kind: 'subhead',
        text: { ja: '支払期限・残高と状態', en: 'Due date, outstanding, and status' },
      },
      {
        kind: 'lede',
        text: {
          ja: '既定の支払条件があれば支払期限が自動で入ります。**残高 ＝ 合計 − 入金済み** です。',
          en: 'With default payment terms, the due date is filled automatically. **Outstanding = total − amount paid.**',
        },
      },
      {
        kind: 'flow',
        chips: [
          { ja: '下書き', en: 'Draft' },
          { ja: '発行済み', en: 'Issued' },
          { ja: '一部入金', en: 'Partially paid' },
          { ja: '入金済み', en: 'Paid' },
        ],
      },
      {
        kind: 'lede',
        text: {
          ja: '支払期限を過ぎた未入金は「**期限超過**」と表示されます。',
          en: 'An unpaid invoice past its due date is flagged **“Overdue.”**',
        },
      },
    ],
  },
  {
    id: 's7',
    title: { ja: '入金管理', en: 'Payments' },
    blocks: [
      {
        kind: 'proc',
        items: [
          {
            ja: '請求書の詳細画面で「**入金を記録**」を開きます。',
            en: 'On the invoice detail screen, open **“Record payment.”**',
          },
          {
            ja: '金額（円）・方法（銀行振込／現金／その他）・備考を入力して記録します。',
            en: 'Enter the amount (yen), method (bank transfer / cash / other), and a note, then record it.',
          },
          {
            ja: '記録すると請求書の入金状態が自動更新されます（全額で「入金済み」、一部で「一部入金」）。',
            en: 'Recording auto-updates the status (“Paid” in full, “Partially paid” in part).',
          },
        ],
      },
      {
        kind: 'note',
        title: { ja: '入金は手動で記録します。', en: 'Payments are recorded manually.' },
        text: {
          ja: '銀行口座との自動連携・自動消込は現時点ではありません。数値の正確さは入金を登録する運用しだいです。',
          en: 'There is no automatic bank-feed or auto-reconciliation yet, so accuracy depends on recording payments.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '残高を超える金額は記録できません（エラーになります）。誤って記録した入金は**取り消せます**（取り消すと状態も戻ります）。',
          en: 'You cannot record more than the outstanding balance (it errors). A payment recorded by mistake can be **voided** (which reverts the status too).',
        },
      },
    ],
  },
  {
    id: 's8',
    title: { ja: 'ダッシュボードの見方', en: 'Reading the dashboard' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '記録された請求・入金から自動計算される指標が並びます。入金を記録する運用が回っているほど、数値は実態に近づきます。',
          en: 'The metrics are computed automatically from recorded invoices and payments. The more consistently payments are recorded, the closer they reflect reality.',
        },
      },
      {
        kind: 'deflist',
        items: [
          {
            term: { ja: '未払い請求書', en: 'Unpaid invoices' },
            desc: {
              ja: '未入金の件数と、残高の合計。',
              en: 'Count of unpaid invoices and the total outstanding.',
            },
          },
          {
            term: { ja: '期限超過', en: 'Overdue' },
            desc: {
              ja: '支払期限を過ぎた未入金の件数。',
              en: 'Count of unpaid invoices past their due date.',
            },
          },
          {
            term: { ja: '当月入金額', en: 'Received this month' },
            desc: {
              ja: '今月記録された入金の合計（前月比つき）。',
              en: 'Total payments recorded this month (with month-over-month change).',
            },
          },
          {
            term: { ja: '残高合計（売掛）', en: 'Outstanding (receivable)' },
            desc: { ja: '未入金の合計。', en: 'Total unpaid.' },
          },
          {
            term: { ja: '当月請求発行額・着地見込み', en: 'Billed this month / projected landing' },
            desc: {
              ja: '今月の発行額と、今のペースでの月末着地予測。前月比・前年同月比も表示。',
              en: 'This month’s issuance and the projected month-end total at the current pace, with prior-month and year-over-year comparisons.',
            },
          },
          {
            term: { ja: '月別推移／日別の積み上がり', en: 'Monthly trend / daily cumulative' },
            desc: {
              ja: '発行額の推移と、当月の積み上がり。',
              en: 'Issuance over time and this month’s running total.',
            },
          },
          {
            term: { ja: '売掛エイジング', en: 'AR aging' },
            desc: {
              ja: '売掛を滞留期間で分類（未到来／1〜30日／31日以上）。',
              en: 'Receivables grouped by how overdue they are (not due / 1–30 days / 31+ days).',
            },
          },
        ],
      },
    ],
  },
  {
    id: 's9',
    title: { ja: '検索・絞り込み・並べ替え・CSV', en: 'Search, filter, sort, CSV' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '各一覧の上部に**フィルタバー**があります。条件を入れて「絞り込む」、「リセット」で解除。「表示中 N 件」で該当件数を確認できます。',
          en: 'Each list has a **filter bar** at the top. Enter conditions and press “Apply,” or “Reset” to clear. “Showing N results” tells you how many match.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '列の見出しをクリックすると**並べ替え**できます。請求・入金・監査ログは **CSV エクスポート**に対応しており、会計ソフトへの取込や控えに使えます。',
          en: 'Click a column header to **sort**. Invoices, payments, and the audit log support **CSV export** for importing into accounting software or keeping records.',
        },
      },
    ],
  },
  {
    id: 's10',
    title: { ja: '監査ログ', en: 'Audit log' },
    admin: true,
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '監査ログは、発行・入金記録・取引先更新などの**操作履歴**を記録します。対象・操作・期間で絞り込み、行を開くと**変更前後の差分**を確認できます。',
          en: 'The audit log records **actions** such as issuing, recording payments, and updating clients. Filter by entity, action, and date range; open a row to see the **before/after diff**.',
        },
      },
    ],
  },
  {
    id: 's11',
    title: { ja: '便利な使い方', en: 'Handy tips' },
    blocks: [
      {
        kind: 'keys',
        groups: [
          {
            heading: { ja: '画面移動（g に続けてキー）', en: 'Navigate (g then a key)' },
            rows: [
              { label: { ja: 'ダッシュボード', en: 'Dashboard' }, caps: ['g', 'd'] },
              { label: { ja: '見積', en: 'Quotes' }, caps: ['g', 'q'] },
              { label: { ja: '請求', en: 'Invoices' }, caps: ['g', 'i'] },
              { label: { ja: '取引先', en: 'Clients' }, caps: ['g', 'c'] },
              { label: { ja: 'ユーザー', en: 'Users' }, caps: ['g', 'u'] },
              { label: { ja: '設定', en: 'Settings' }, caps: ['g', 's'] },
              { label: { ja: '監査ログ', en: 'Audit log' }, caps: ['g', 'a'] },
              { label: { ja: 'ショートカット一覧', en: 'Shortcut list' }, caps: ['?'] },
            ],
          },
          {
            heading: { ja: '一覧・編集での操作', en: 'In lists and forms' },
            rows: [
              { label: { ja: '行を移動', en: 'Move rows' }, caps: ['j', 'k'] },
              { label: { ja: '開く', en: 'Open' }, caps: ['Enter'] },
              { label: { ja: '新規作成', en: 'New' }, caps: ['n'] },
              { label: { ja: '検索', en: 'Search' }, caps: ['/'] },
              { label: { ja: '送信', en: 'Submit' }, caps: ['⌘/Ctrl', 'Enter'] },
              { label: { ja: '閉じる', en: 'Close' }, caps: ['Esc'] },
            ],
          },
        ],
      },
      {
        kind: 'note',
        title: { ja: '言語切替', en: 'Language' },
        text: {
          ja: '画面の言語切替（日本語 / English）で、いつでも表示言語を変えられます。',
          en: 'Use the language switch (日本語 / English) to change the display language at any time.',
        },
      },
    ],
  },
  {
    id: 's12',
    title: { ja: 'よくある質問', en: 'FAQ' },
    blocks: [
      {
        kind: 'faq',
        items: [
          {
            q: {
              ja: '請求書を発行した後に内容は直せますか？',
              en: 'Can I edit an invoice after issuing it?',
            },
            a: {
              ja: 'いいえ。発行で請求番号が採番され、内容が確定します（適格請求書として確定するため）。修正が必要な場合は、**下書きのうちに内容を確認**してから発行してください。',
              en: 'No. Issuing assigns the invoice number and finalizes the contents (it becomes a finalized qualified invoice). If you need changes, **review everything while it is still a draft**, then issue.',
            },
          },
          {
            q: {
              ja: '「発行できませんでした。登録番号をご確認ください」と出ます',
              en: 'I get “Could not issue — please check the registration number.”',
            },
            a: {
              ja: '会社設定の「登録番号（インボイス）」が未登録です。設定 →「基本情報」で `T＋13桁` の登録番号を保存してから、もう一度発行してください。',
              en: 'The “registration number (invoice)” in company Settings is missing. Go to Settings → Basic info, save the `T + 13-digit` number, then issue again.',
            },
          },
          {
            q: {
              ja: '適格請求書（インボイス）とは？登録番号はどこに入れますか？',
              en: 'What is a qualified invoice, and where do I enter the registration number?',
            },
            a: {
              ja: '登録番号や税率区分を記載した、仕入税額控除に使える請求書です。会社設定に登録番号を入れておけば、**発行時に自動で請求書へ記載**されます。',
              en: 'It’s an invoice listing the registration number and tax-rate breakdown, usable for input-tax credit. Once set in company Settings, it is **printed on the invoice automatically when you issue**.',
            },
          },
          {
            q: {
              ja: '入金が銀行と自動で同期されません',
              en: 'Payments don’t sync with my bank automatically.',
            },
            a: {
              ja: '仕様です。入金は請求書の詳細画面から**手動で記録**します。記録すると、未払い・売掛・当月入金額などに反映されます。',
              en: 'That’s by design. Record payments **manually** from the invoice detail screen. Once recorded, they flow into unpaid / receivable / received-this-month figures.',
            },
          },
          {
            q: {
              ja: '見積から請求への変換で何が引き継がれますか？',
              en: 'What carries over when I convert a quote to an invoice?',
            },
            a: {
              ja: '取引先・明細（品目・数量・単価・税率）・金額を引き継いで、請求書の下書きを作成します。発行は別途「発行する」で行います。',
              en: 'The client, line items (item, quantity, unit price, tax rate), and amounts carry over into an invoice draft. Issuing is a separate step via “Issue.”',
            },
          },
          {
            q: {
              ja: 'パスワードを忘れた／ユーザーを追加したい',
              en: 'I forgot my password / want to add a user.',
            },
            a: {
              ja: '管理者がユーザー一覧から対象を編集し、新しいパスワードを設定できます。新規ユーザーは「**ユーザーを作成**」から追加します。',
              en: 'An admin can edit the user from the Users list and set a new password. Add new users via **“Create user.”**',
            },
          },
          {
            q: { ja: 'データはどこに保存されますか？', en: 'Where is my data stored?' },
            a: {
              ja: '自己ホスト型なので、**あなたの環境のデータベース**に保存されます。請求ロジックが外部サービスへデータを送信することはありません。',
              en: 'It’s self-hosted, so your data is stored in **your own database**. The billing logic never sends data to an external service.',
            },
          },
        ],
      },
    ],
  },
  {
    id: 'disclaimer',
    title: { ja: '免責事項・ご利用にあたって', en: 'Disclaimer & terms of use' },
    blocks: [
      {
        kind: 'lede',
        text: {
          ja: '本ソフトウェア（NeNe Invoice）は、見積・請求・入金管理の作成を補助するツールであり、税務・会計・法務に関する助言を提供するものではありません。作成された請求書・帳票の内容、税率・税区分・登録番号の正確性、適格請求書としての要件充足、税務申告および帳簿・書類の保存（電子帳簿保存法等）については、最終的にご利用者ご自身および顧問税理士の責任でご確認ください。',
          en: 'This software (NeNe Invoice) is a tool that helps you prepare quotes, invoices, and payment records. It does not provide tax, accounting, or legal advice. The contents of the documents it produces, the accuracy of tax rates / categories / registration numbers, whether they meet qualified-invoice requirements, and tax filing and record retention (including under the Electronic Books Preservation Act) are ultimately your responsibility and that of your tax accountant.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '本ソフトウェアは MIT ライセンスに基づき「現状有姿（AS IS）」で提供され、明示・黙示を問わずいかなる保証も行いません。本ソフトウェアの使用または使用不能から生じたいかなる損害についても、作者および権利者は責任を負いません。',
          en: 'This software is provided “AS IS” under the MIT License, without warranty of any kind, express or implied. The authors and copyright holders are not liable for any damages arising from the use of, or inability to use, this software.',
        },
      },
      {
        kind: 'lede',
        text: {
          ja: '自己ホスト型のため、データのバックアップ・保管・セキュリティおよび法定保存期間の遵守は、運用される事業者の責任となります。また、法令・制度の改正への追随を保証するものではありません。',
          en: 'Because it is self-hosted, backing up, storing, and securing your data, and complying with statutory retention periods, are the responsibility of the operating business. Nor does it guarantee that it stays up to date with changes in laws or regulations.',
        },
      },
    ],
  },
]
