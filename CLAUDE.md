# CLAUDE.md — NeNe Invoice

Claude Code / AI エージェント向け実行ガイド。詳細ポリシーの正本は `docs/` 以下。

---

## プロダクト一言要約

自己ホスト型 **見積・請求・入金管理** OSS（MIT）。日本 SMB 向け **適格請求書** 対応。NENE2 上の sibling product — Records / Corpus / Concierge と HTTP 連携のみ。

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
| Phase 0 プロダクト設計 | 🔄 Issue #3 PR 待ち |
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

---

## アーキテクチャ規約（概要）

```
Handler → UseCase → RepositoryInterface → PdoRepository
```

- Namespace: `NeneInvoice\`
- ドメイン別フォルダ（`Client/`, `Quote/`, `Invoice/`, `Payment/` 等）
- レイヤー別フォルダ禁止（`src/Handlers/` 等）
- JSON: **snake_case** 固定
- 金額: **integer cents**（float 禁止）
- SQL: `Pdo*Repository` 内のみ

詳細: `docs/development/backend-standards.md`, `docs/development/naming-conventions.md`

---

## Source of Truth

| 目的 | ドキュメント |
| --- | --- |
| 現在のタスク | `docs/todo/current.md` |
| ロードマップ | `docs/roadmap.md` |
| プロダクトビジョン | `docs/explanation/product-vision.md` |
| ワークフロー | `docs/workflow.md` |
| NENE2 継承 | `docs/inheritance-from-nene2.md` |
