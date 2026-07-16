# CLAUDE.md — NeNe Invoice

Claude Code / AI エージェント向け実行ガイド。詳細ポリシーの正本は `docs/` 以下。

---

## プロダクト一言要約

自己ホスト型 **見積・請求・入金管理** OSS（MIT）。日本 SMB 向け **適格請求書** 対応。**マルチテナント前提**（組織＝テナント、superadmin が組織を / admin がユーザーを管理 — ADR 0006）。NENE2 上の sibling product — Records / Corpus / Concierge と HTTP 連携のみ。

```
Admin UI (React)  ──→  NeNe Invoice API (NENE2/PHP 8.4)  ──→  MySQL
Ops / MCP         ──→         │
                               ↓ HTTP read-only / webhook（任意）
                    NeNe Records / NeNe Concierge / 外部 API
```

---

## 現在の開発状況

> **最終更新: 2026-07-05**（`docs/todo/current.md` が正本）

| フェーズ | 状態 |
| --- | --- |
| Phase 0 ガバナンス／プロダクト設計 | ✅ 完了 |
| Phase 1 コア請求 API（認証・マルチテナント・取引先・見積・請求・入金） | ✅ 完了 |
| Phase 2 管理 UI（React）＋ 適格請求書 PDF ＋ ダッシュボード ＋ 監査ログ ＋ ja/en | ✅ 完了 |
| Phase 3 Tier A 共有ホスティング（インストーラ・リリース ZIP・運用ガイド） | ✅ 完了 |
| セキュリティ診断 Round 1–2 | ✅ 完了（指摘修正済み） |
| Phase 4 エコシステム連携（財務クラスタ・決済GW・managed） | 🔄 進行中。実装/設計済み: CSV・検索/フィルタ/ソート・言語切替・service token・カード決済（PAY.JP・ADR 0013）・認証セッション永続化（ADR 0014）・**定期請求（#519–#523＋実行ルート配線 #526, `/recurring`）**・**銀行入金 自動消込（#505・`/bank-reconciliation`＝CSV取込→名義辞書照合→確認起票、残 ⑥ write-off/過入金按分 #543 は税理士ゲート）**・**型B マルチテナント（superadmin プロビジョニング＋顧問先 SPA を `/{slug}/` 配下・#552/#556/#558/#560）**・**インストーラの NENE2 `Nene2\Install` toolkit consumer 化（#562・NENE2 ^1.6）**・**NeNe Clear 実接続（契約検証済み）**・**MFA 設計（#524・`docs/design/mfa-totp.md`・Suite ADR 0025 準拠）**。残り: 自動 issue（採番=税理士ゲート）・MFA 実装・#505 ⑥ write-off/#543・一括発行 #527・業種テンプレ #528/#513・Records カタログ・Concierge webhook・#464/#465。戦略: clear=現金の楔 / **invoice=財務クラスタの土台** / Suite=managed クラウド。詳細 → `docs/handover/2026-07-03-typeb-phase2-complete-and-cleanup.md`、ペルソナ評価 → `docs/research/persona-review-2026-06-27/` |

---

## ワークフロー

1. **GitHub Issue を作成**（または番号を確認）する。Issue なしに編集しない。
2. `docs/roadmap.md`, `docs/todo/current.md`, 関連 Issue/PR を確認する。
3. `main` から `type/issue-number-summary` ブランチを切る。
4. 実装 → 品質チェック → commit。
5. PR 作成：`Closes #N` + セルフレビューチェックリスト名を本文に記載。
6. CI green → merge → ローカル `main` sync。
7. 作業した日は日報を **`docs/daily/YYYY-MM-DD.md`** に残す。書式・置き場の正本はフリート規約 **`_work/daily-report-convention.md`**（2026-07-17 確定・全製品リポ `docs/daily/` に統一）。索引と `_work/` との線引きは `docs/daily/README.md`。

**コミット形式:**
```
<type>(<scope>): <日本語の説明> (#<issue>)
```

**絶対禁止:**
- `main` への直接 commit/push
- Issue なしの変更
- `.env` / トークン / パスワードのコミット
- sibling 製品への請求ロジック統合（ADR 0002）

**絶対順守:**
- **会計・税務コンプライアンス完全順守**（非交渉）。請求・税・採番・PDF・保存に関わる変更は `docs/explanation/accounting-compliance.md` と `docs/review/compliance.md` で確認必須。逸脱は ADR ＋ 税理士確認なしに禁止。正本: `docs/explanation/accounting-compliance.md`
- **命名規則の絶対順守・タイポ厳禁**。全識別子（エンティティ／状態値／JSON・DB フィールド／Problem Details slug／operationId）は用語レジストリ `docs/explanation/terminology.md` と **完全一致**。スペルゆれ・未登録語はマージ禁止。追加・改名は同一 PR でレジストリ更新必須。

---

## アーキテクチャ規約（概要）

```
Handler → UseCase → RepositoryInterface → PdoRepository
```

- Namespace: `NeneInvoice\`
- ドメイン別フォルダ（`Organization/`, `Auth/`, `User/`, `Client/`, `Quote/`, `Invoice/`, `Payment/` 等）
- レイヤー別フォルダ禁止（`src/Handlers/` 等）
- JSON: **snake_case** 固定
- 金額: **integer cents**（float 禁止）
- **マルチテナント**: テナントスコープの全テーブル/クエリに `organization_id`。superadmin のみクロステナント（ADR 0006）
- SQL: `Pdo*Repository` 内のみ

詳細: `docs/development/backend-standards.md`, `docs/development/naming-conventions.md`, `docs/explanation/terminology.md`（用語レジストリ＝唯一の真実）

---

## ローカル開発ポート（固定）

複数アプリ並行開発によるポート競合を防ぐため、**NeNe Invoice は 85** レンジに固定**する。
他プロダクトのレンジ（NENE2: 82**、NeNe Clear: 83**、NeNe Profile: 84**、NeNe Records: 180**）と重複させてはならない。

| サービス | ホストポート | 用途 |
| --- | --- | --- |
| PHP dev server | **8510** | `php -S localhost:8510 -t public_html public_html/index.php`（ホスト直実行） |
| Docker app（Apache, API + UI） | **8510** | `docker compose up`。PHP dev server と排他（同一ポート） |
| Vite dev server | **5185** | `npm run dev`（frontend/） |
| Docker MySQL | **3585** | `compose.yaml` の MySQL（env `NENE_INVOICE_MYSQL_PORT`） |
| Docker phpMyAdmin | **8581** | `compose.yaml`（env `NENE_INVOICE_PHPMYADMIN_PORT`） |
| Mailpit SMTP | **1585** | `docker compose up -d mailpit` |
| Mailpit Web UI | **8585** | メール受信確認 http://localhost:8585 |
| セキュリティ診断 App | **8590** | `docs/security/harness/` |
| セキュリティ診断 MySQL | **3385** | 同上 |

**絶対禁止:** 上記以外のポートを `compose.yaml` / `vite.config.ts` / `.env.example` に記載しない。新規コンテナを追加する場合も 85** 内（DB 等で 33** が必要なら 35** 系）から選ぶ。

### 起動方法（2 通り）

- **Docker（推奨・最短）**: `docker compose up -d --build` → http://localhost:8510 で API + 管理 UI。DB は MySQL、マイグレーション・dev シードは自動。クローン直後でもログイン可（`admin@example.com` / `password123`）。
- **ホスト直実行**: `php -S localhost:8510 -t public_html public_html/index.php`（SQLite・要絶対パス）+ `npm run dev --prefix frontend`（Vite 5185）。FE の HMR が必要なときはこちら、または Docker と併用。

---

## Source of Truth

| 目的 | ドキュメント |
| --- | --- |
| **会計・税務コンプライアンス（拘束）** | `docs/explanation/accounting-compliance.md` |
| **用語レジストリ（識別子の正準スペル）** | `docs/explanation/terminology.md` |
| 現在のタスク | `docs/todo/current.md` |
| ロードマップ | `docs/roadmap.md` |
| プロダクトビジョン | `docs/explanation/product-vision.md` |
| ワークフロー | `docs/workflow.md` |
| NENE2 継承 | `docs/inheritance-from-nene2.md` |
