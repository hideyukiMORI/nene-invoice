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

> **最終更新: 2026-05-29**（`docs/todo/current.md` が正本）

| フェーズ | 状態 |
| --- | --- |
| Phase 0 ガバナンス | ✅ 完了（Issue #1） |
| Phase 0 プロダクト設計 | ✅ 完了（Issue #3 / #9 / #11） |
| Phase 0+ ランタイム scaffold | 🔲 Issue #4 以降 |

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
