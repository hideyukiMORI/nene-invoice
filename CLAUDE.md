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

> **最終更新: 2026-06-04**（`docs/todo/current.md` が正本）

| フェーズ | 状態 |
| --- | --- |
| Phase 0 ガバナンス／プロダクト設計 | ✅ 完了 |
| Phase 1 コア請求 API（認証・マルチテナント・取引先・見積・請求・入金） | ✅ 完了 |
| Phase 2 管理 UI（React）＋ 適格請求書 PDF ＋ ダッシュボード ＋ 監査ログ ＋ ja/en | ✅ 完了 |
| Phase 3 Tier A 共有ホスティング（インストーラ・リリース ZIP・運用ガイド） | ✅ 完了 |
| セキュリティ診断 Round 1–2 | ✅ 完了（指摘修正済み） |
| Phase 4 エコシステム連携（Records / Concierge・決済GW） | 🔄 進行中（CSV エクスポート・一覧の検索/フィルタ/ソート・言語切替UI は実装済み） |

---

## ワークフロー

1. **GitHub Issue を作成**（または番号を確認）する。Issue なしに編集しない。
2. `docs/roadmap.md`, `docs/todo/current.md`, 関連 Issue/PR を確認する。
3. `main` から `type/issue-number-summary` ブランチを切る。
4. 実装 → 品質チェック → commit。
5. PR 作成：`Closes #N` + セルフレビューチェックリスト名を本文に記載。
6. CI green → merge → ローカル `main` sync。

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
| PHP dev server | **8510** | `php -S localhost:8510 -t public_html` |
| Vite dev server | **5185** | `npm run dev`（frontend/） |
| Mailpit SMTP | **1585** | `docker compose up -d mailpit` |
| Mailpit Web UI | **8585** | メール受信確認 http://localhost:8585 |
| セキュリティ診断 App | **8590** | `docs/security/harness/` |
| セキュリティ診断 MySQL | **3385** | 同上 |

**絶対禁止:** 上記以外のポートを `docker-compose.yml` / `vite.config.ts` / `.env.example` に記載しない。新規コンテナを追加する場合も 85** 内から選ぶ。

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
