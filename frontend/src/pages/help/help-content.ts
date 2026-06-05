/**
 * Help-page content (Issue #306). Co-located bilingual ja/en pairs — like
 * `shared/keyboard/shortcuts-data.ts` — rather than locale-catalog keys, since
 * the page is long-form prose with lists and steps that don't fit flat keys.
 * `HelpPage` picks ja or en from the active locale.
 *
 * Keep wording aligned with the real UI labels (the buttons / statuses a
 * beginner actually sees), so the guide stays accurate as a walkthrough.
 */

/** A translated string: primary ja + secondary en. */
export interface Bi {
  ja: string
  en: string
}

export type HelpBlock =
  | { kind: 'p'; text: Bi }
  | { kind: 'steps'; items: Bi[] }
  | { kind: 'list'; items: Bi[] }
  | { kind: 'note'; text: Bi }
  | { kind: 'defs'; items: { term: Bi; desc: Bi }[] }

export interface HelpSection {
  /** Anchor id, also used by the table of contents. */
  id: string
  title: Bi
  lead?: Bi
  blocks: HelpBlock[]
}

export interface HelpFaqItem {
  q: Bi
  a: Bi
}

export const HELP_LABELS = {
  intro: {
    ja: 'NeNe Invoice を初めて使う方向けの操作ガイドです。見積から請求・入金までの流れと、つまずきやすいポイントをまとめています。',
    en: 'A getting-started guide for people new to NeNe Invoice. It walks through the quote → invoice → payment flow and the spots people commonly get stuck on.',
  },
  toc: { ja: '目次', en: 'Contents' },
  faqTitle: { ja: 'よくある質問', en: 'Frequently asked questions' },
  backToTop: { ja: '↑ 目次へ戻る', en: '↑ Back to contents' },
} as const

export const HELP_SECTIONS: HelpSection[] = [
  {
    id: 'quickstart',
    title: { ja: 'はじめに（クイックスタート）', en: 'Getting started (quick start)' },
    lead: {
      ja: 'NeNe Invoice は、見積・請求・入金をまとめて管理する自己ホスト型システムです。日本の適格請求書（インボイス）に対応しています。',
      en: 'NeNe Invoice is a self-hosted system for managing quotes, invoices, and payments together. It supports Japan’s qualified invoice (invoice system) format.',
    },
    blocks: [
      {
        kind: 'p',
        text: {
          ja: '全体の流れはシンプルです。見積書を作り、承認されたら請求書に変換し、請求書を「発行」して取引先へ送付し、入金されたら記録します。ダッシュボードで未払い・売掛・入金の状況をいつでも把握できます。',
          en: 'The overall flow is simple: create a quote, convert it to an invoice once accepted, “issue” the invoice and send it to the client, then record the payment when it arrives. The dashboard shows your unpaid / receivable / payment situation at any time.',
        },
      },
      {
        kind: 'steps',
        items: [
          {
            ja: '会社情報を登録する（設定）。特に「登録番号（T＋13桁）」は請求書の発行に必須です。',
            en: 'Register your company info (Settings). In particular the “registration number (T + 13 digits)” is required to issue invoices.',
          },
          {
            ja: '取引先を登録する（取引先 → 取引先を作成）。',
            en: 'Add your clients (Clients → Create client).',
          },
          {
            ja: '見積書、または請求書を作成する。',
            en: 'Create a quote, or an invoice.',
          },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: '自己ホスト型のため、データはあなたの環境のデータベースに保存されます。請求情報が外部サービスへ送信されることはありません。',
          en: 'Because it is self-hosted, your data lives in your own database. Billing information is never sent to an external service.',
        },
      },
    ],
  },
  {
    id: 'glossary',
    title: { ja: '用語ミニ辞典', en: 'Mini glossary' },
    blocks: [
      {
        kind: 'defs',
        items: [
          {
            term: { ja: '適格請求書（インボイス）', en: 'Qualified invoice' },
            desc: {
              ja: '登録番号や税率区分を記載した、仕入税額控除に使える請求書。NeNe Invoice の請求書は「発行」時にこの形式で採番されます。',
              en: 'An invoice that lists the registration number and tax-rate breakdown so it can be used for input-tax credit. Invoices here are numbered in this format when you “issue” them.',
            },
          },
          {
            term: { ja: '登録番号', en: 'Registration number' },
            desc: {
              ja: '適格請求書発行事業者の番号（T＋13桁）。会社設定に登録します。',
              en: 'The qualified-invoice issuer number (T + 13 digits). Set it in company Settings.',
            },
          },
          {
            term: { ja: '締め日／支払サイト', en: 'Closing day / payment terms' },
            desc: {
              ja: '「毎月◯日締め・翌月△日払い」のような支払条件。設定しておくと請求書の支払期限が自動計算されます。',
              en: 'Terms like “close on the Nth, pay on the Mth of next month.” Once set, an invoice’s due date is calculated automatically.',
            },
          },
          {
            term: { ja: '残高（売掛）', en: 'Outstanding (accounts receivable)' },
            desc: {
              ja: '請求済みで、まだ入金されていない金額。合計から入金済みを引いた額です。',
              en: 'Billed but not yet received. The total minus what has been paid.',
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
    id: 'setup',
    title: { ja: '初期設定', en: 'Initial setup' },
    lead: {
      ja: '最初に会社情報・既定値・ユーザーを整えておくと、以降の操作がスムーズです。',
      en: 'Setting up your company info, defaults, and users first makes everything afterward smoother.',
    },
    blocks: [
      {
        kind: 'p',
        text: {
          ja: '会社情報（設定）— 法人名（必須）・住所・電話・メール、振込先（銀行名／支店名／口座種別／口座番号）、そして登録番号（T＋13桁）を登録します。登録番号は請求書の発行に必須で、未登録だと発行できません。',
          en: 'Company info (Settings) — legal name (required), address, phone, email, bank details (bank / branch / account type / number), and the registration number (T + 13 digits). The registration number is required to issue invoices; without it you cannot issue.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '請求・見積の既定 — 「見積の有効期限（日数）」を入れると、見積作成時に有効期限が自動で入ります。「締め日・支払月・支払日」を設定すると、請求書の支払期限が自動計算されます（例: 末日締め・翌月末払い）。空欄なら毎回手入力です。',
          en: 'Invoice / quote defaults — set “quote validity (days)” to auto-fill a quote’s expiry on creation. Set “closing day / payment month / pay day” to auto-calculate an invoice’s due date (e.g. close at month-end, pay end of next month). Leave blank to enter it each time.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: 'ユーザーとロール — 管理者はユーザー管理を含む操作、メンバーは日常の見積・請求・入金、閲覧者は閲覧のみ、スーパー管理者は組織（テナント）全体の管理を担います。',
          en: 'Users and roles — admins can manage users and operate the system, members handle day-to-day quotes / invoices / payments, viewers are read-only, and superadmins manage the whole organization (tenant).',
        },
      },
    ],
  },
  {
    id: 'clients',
    title: { ja: '取引先', en: 'Clients' },
    blocks: [
      {
        kind: 'steps',
        items: [
          {
            ja: '「取引先」→「取引先を作成」を開きます。',
            en: 'Open Clients → Create client.',
          },
          {
            ja: '名称（必須）・担当者・メール・登録番号などを入力して保存します。',
            en: 'Enter name (required), contact, email, registration number, etc., then save.',
          },
        ],
      },
      {
        kind: 'p',
        text: {
          ja: '取引先のメールを登録しておくと、請求書の「メールで送信」が使えます。編集は一覧の行から行います。',
          en: 'Registering the client’s email lets you use “Send by email” on an invoice. Edit a client from its row in the list.',
        },
      },
    ],
  },
  {
    id: 'quotes',
    title: { ja: '見積書', en: 'Quotes' },
    blocks: [
      {
        kind: 'steps',
        items: [
          {
            ja: '「見積書」→「見積書を作成」を開きます。',
            en: 'Open Quotes → Create quote.',
          },
          {
            ja: '取引先を選び、品目・数量・単価・税率を入力します。行は必要なだけ追加できます。',
            en: 'Pick a client and enter line items (item, quantity, unit price, tax rate). Add as many rows as you need.',
          },
          {
            ja: '有効期限を設定して保存すると「下書き」になります。',
            en: 'Set the expiry and save — it becomes a “Draft.”',
          },
        ],
      },
      {
        kind: 'p',
        text: {
          ja: '状態の流れ: 下書き → 送付する（送付済み）→ 承認する（承認済み）／却下する／期限切れにする。詳細画面の操作ボタンで進めます。',
          en: 'Status flow: Draft → Send (Sent) → Accept (Accepted) / Reject / Mark expired. Move through these with the action buttons on the detail screen.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: 'PDF をダウンロードして送付できます。承認済みの見積は「請求書に変換する」で、内容（取引先・明細・金額）を引き継いだ請求書の下書きを作成できます。',
          en: 'You can download a PDF to send. An accepted quote can be turned into an invoice with “Convert to invoice,” which carries over the client, line items, and amounts as an invoice draft.',
        },
      },
    ],
  },
  {
    id: 'invoices',
    title: { ja: '請求書', en: 'Invoices' },
    blocks: [
      {
        kind: 'steps',
        items: [
          {
            ja: '「請求書」→「請求書を作成」、または承認済み見積から変換します。最初は「下書き（未採番）」です。',
            en: 'Open Invoices → Create invoice, or convert from an accepted quote. It starts as a “Draft (not yet numbered).”',
          },
          {
            ja: '内容を確認し「発行する（適格請求書）」を押します。確認後に請求番号が採番され「発行済み」になります。',
            en: 'Review it and press “Issue (qualified invoice).” After confirmation it gets an invoice number and becomes “Issued.”',
          },
          {
            ja: '発行後は内容が確定します（編集できません）。下書きのうちに必ず確認してください。',
            en: 'Once issued, the contents are final (no longer editable). Always check while it is still a draft.',
          },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: '発行には会社設定の登録番号（T＋13桁）が必須です。未登録だと「発行できませんでした。会社情報の登録番号をご確認ください」と表示されます → 設定で登録してください。',
          en: 'Issuing requires the company registration number (T + 13 digits). Without it you’ll see “Could not issue — please check the registration number,” so register it in Settings.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '送付方法は3つ: ①PDF をダウンロード ②取引先メールへ「メールで送信」 ③「クライアント向けリンクを生成」（期限つきの閲覧／ダウンロードURL。ログイン不要で相手が開けます）。',
          en: 'Three ways to deliver it: (1) download a PDF, (2) “Send by email” to the client, or (3) “Generate a client link” — a time-limited view/download URL the recipient can open without logging in.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '支払期限・残高: 既定の支払条件があれば支払期限が自動で入ります。残高＝合計−入金済みです。',
          en: 'Due date / outstanding: if you set default payment terms, the due date is filled automatically. Outstanding = total − amount paid.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '状態: 下書き → 発行済み → 一部入金 → 入金済み。支払期限を過ぎた未入金は「期限超過」と表示されます。',
          en: 'Status: Draft → Issued → Partially paid → Paid. An unpaid invoice past its due date is flagged “Overdue.”',
        },
      },
    ],
  },
  {
    id: 'payments',
    title: { ja: '入金管理', en: 'Payments' },
    blocks: [
      {
        kind: 'steps',
        items: [
          {
            ja: '請求書の詳細画面で「入金を記録」を開きます。',
            en: 'On the invoice detail screen, open “Record payment.”',
          },
          {
            ja: '金額（円）・方法（銀行振込／現金／その他）・備考を入力して記録します。',
            en: 'Enter the amount (yen), method (bank transfer / cash / other), and a note, then record it.',
          },
          {
            ja: '記録すると請求書の入金状態が自動更新されます（全額で「入金済み」、一部で「一部入金」）。',
            en: 'Recording auto-updates the invoice status (“Paid” in full, “Partially paid” in part).',
          },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: '入金は手動で記録します。銀行口座との自動連携・自動消込は現時点ではありません。数値の正確さは入金を登録する運用しだいです。',
          en: 'Payments are recorded manually. There is currently no automatic bank-feed or auto-reconciliation, so accuracy depends on actually recording payments.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '残高を超える金額は記録できません（エラーになります）。誤って記録した入金は取り消せます（取り消すと状態も戻ります）。',
          en: 'You cannot record more than the outstanding balance (it errors). A payment recorded by mistake can be voided, which reverts the status too.',
        },
      },
    ],
  },
  {
    id: 'dashboard',
    title: { ja: 'ダッシュボードの見方', en: 'Reading the dashboard' },
    blocks: [
      {
        kind: 'list',
        items: [
          {
            ja: '未払い請求書 — 未入金の件数と、残高の合計。',
            en: 'Unpaid invoices — the count and the total outstanding.',
          },
          {
            ja: '期限超過 — 支払期限を過ぎた未入金の件数。',
            en: 'Overdue — count of unpaid invoices past their due date.',
          },
          {
            ja: '当月入金額 — 今月記録された入金の合計（前月比つき）。',
            en: 'Received this month — total payments recorded this month (with month-over-month change).',
          },
          {
            ja: '残高合計（売掛） — 未入金の合計。',
            en: 'Outstanding (receivable) — total unpaid.',
          },
          {
            ja: '当月請求発行額・着地見込み — 今月の発行額と、今のペースでの月末着地予測。前月比・前年同月比も表示。',
            en: 'Billed this month / projected landing — what you’ve issued this month and the projected month-end total at the current pace, with prior-month and year-over-year comparisons.',
          },
          {
            ja: '月別推移／日別の積み上がり — 発行額の推移と、当月の積み上がり。',
            en: 'Monthly trend / daily cumulative — issuance over time and this month’s running total.',
          },
          {
            ja: '売掛エイジング — 売掛を滞留期間で分類。',
            en: 'AR aging — receivables grouped by how overdue they are.',
          },
        ],
      },
      {
        kind: 'note',
        text: {
          ja: 'これらは記録された請求・入金から自動計算されます。入金を記録する運用が回っているほど、数値は実態に近づきます。',
          en: 'These are computed automatically from recorded invoices and payments. The more consistently payments are recorded, the closer the numbers reflect reality.',
        },
      },
    ],
  },
  {
    id: 'lists',
    title: { ja: '検索・絞り込み・並べ替え・CSV', en: 'Search, filter, sort, CSV' },
    blocks: [
      {
        kind: 'p',
        text: {
          ja: '各一覧の上部にフィルタバーがあります。条件を入れて「絞り込む」、「リセット」で解除。「表示中 N 件」で該当件数を確認できます。',
          en: 'Each list has a filter bar at the top. Enter conditions and press “Apply,” or “Reset” to clear. “Showing N results” tells you how many match.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '列の見出しをクリックすると並べ替えできます。請求・入金・監査ログは CSV エクスポートに対応しており、会計ソフトへの取込や控えに使えます。',
          en: 'Click a column header to sort. Invoices, payments, and the audit log can be exported as CSV for importing into accounting software or keeping records.',
        },
      },
    ],
  },
  {
    id: 'audit',
    title: { ja: '監査ログ（管理者専用）', en: 'Audit log (admins only)' },
    blocks: [
      {
        kind: 'p',
        text: {
          ja: '監査ログは、発行・入金記録・取引先更新などの操作履歴を記録します。対象・操作・期間で絞り込み、行を開くと変更前後の差分を確認できます。',
          en: 'The audit log records actions such as issuing, recording payments, and updating clients. Filter by entity, action, and date range; open a row to see the before/after diff.',
        },
      },
    ],
  },
  {
    id: 'tips',
    title: { ja: '便利な使い方', en: 'Handy tips' },
    blocks: [
      {
        kind: 'p',
        text: {
          ja: 'キーボードショートカット: g に続けて d（ダッシュボード）/ q（見積）/ i（請求）/ c（取引先）/ u（ユーザー）/ s（設定）/ a（監査ログ）で画面移動。一覧では j・k で行移動、Enter で開く。n で新規作成、/ で検索、⌘（Windows は Ctrl）+ Enter で送信、Esc で閉じる。? でいつでも一覧を表示できます。',
          en: 'Keyboard shortcuts: press g then d (Dashboard) / q (Quotes) / i (Invoices) / c (Clients) / u (Users) / s (Settings) / a (Audit log) to navigate. In lists, j・k move rows and Enter opens. n creates new, / focuses search, ⌘ (Ctrl on Windows) + Enter submits, Esc closes. Press ? anytime for the full list.',
        },
      },
      {
        kind: 'p',
        text: {
          ja: '言語切替: 画面の言語切替（日本語 / English）で、いつでも表示言語を変えられます。',
          en: 'Language: use the language switch (日本語 / English) to change the display language at any time.',
        },
      },
    ],
  },
]

export const HELP_FAQ: HelpFaqItem[] = [
  {
    q: {
      ja: '請求書を発行した後に内容は直せますか？',
      en: 'Can I edit an invoice after issuing it?',
    },
    a: {
      ja: 'いいえ。発行で請求番号が採番され、内容が確定します（適格請求書として確定するため）。修正が必要な場合は、下書きのうちに内容を確認してから発行してください。',
      en: 'No. Issuing assigns the invoice number and finalizes the contents (it becomes a finalized qualified invoice). If you need changes, review everything while it is still a draft, then issue.',
    },
  },
  {
    q: {
      ja: '「発行できませんでした。登録番号をご確認ください」と出ます',
      en: 'I get “Could not issue — please check the registration number.”',
    },
    a: {
      ja: '会社設定の「登録番号（インボイス）」が未登録です。設定 →「基本情報」で T＋13桁の登録番号を保存してから、もう一度発行してください。',
      en: 'The “registration number (invoice)” in company Settings is missing. Go to Settings → Basic info, save the T + 13-digit number, then issue again.',
    },
  },
  {
    q: {
      ja: '適格請求書（インボイス）とは？登録番号はどこに入れますか？',
      en: 'What is a qualified invoice, and where do I enter the registration number?',
    },
    a: {
      ja: '登録番号や税率区分を記載した、仕入税額控除に使える請求書です。会社設定に登録番号を入れておけば、発行時に自動で請求書へ記載されます。',
      en: 'It’s an invoice listing the registration number and tax-rate breakdown, usable for input-tax credit. Once the registration number is set in company Settings, it is printed on the invoice automatically when you issue.',
    },
  },
  {
    q: {
      ja: '入金が銀行と自動で同期されません',
      en: 'Payments don’t sync with my bank automatically.',
    },
    a: {
      ja: '仕様です。入金は請求書の詳細画面から手動で記録します。記録すると、未払い・売掛・当月入金額などに反映されます。',
      en: 'That’s by design. Record payments manually from the invoice detail screen. Once recorded, they flow into unpaid / receivable / received-this-month figures.',
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
      ja: '管理者がユーザー一覧から対象を編集し、新しいパスワードを設定できます。新規ユーザーは「ユーザーを作成」から追加します。',
      en: 'An admin can edit the user from the Users list and set a new password. Add new users via “Create user.”',
    },
  },
  {
    q: { ja: 'データはどこに保存されますか？', en: 'Where is my data stored?' },
    a: {
      ja: '自己ホスト型なので、あなたの環境のデータベースに保存されます。請求ロジックが外部サービスへデータを送信することはありません。',
      en: 'It’s self-hosted, so your data is stored in your own database. The billing logic never sends data to an external service.',
    },
  },
]
