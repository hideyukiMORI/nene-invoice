# NeNe Invoice インストーラ — デザインハンドオフ用モックアップ

共有ホスティング向け Web インストーラ（`public_html/install.php`）の **全画面・全状態・全コピー** を収めた静的 HTML 一式です。ClaudeDesign が再デザインするための入力資料。

## 開き方

ブラウザで **`index.html`** を開くと目次・フロー・デザイン方針が見られます。各画面 HTML は単体でも開けます（共通スタイルは `assets/mockup.css`）。

## 収録物

| ファイル | 内容 |
| --- | --- |
| `index.html` | 目次・画面フロー・凡例・デザイン方針 |
| `01-requirements.html` | 要件チェック（合格/不合格） |
| `02-database.html` | ステップ1: DB 接続（ツールチップ付き） |
| `03-admin.html` | ステップ2: 管理者設定 |
| `04-complete.html` | ステップ3: 完了（install.php 削除警告） |
| `90-states-and-errors.html` | 異常系・特殊状態の集約（403/接続エラー/バリデーション/ローディング/要件不合格） |
| `99-copy-deck.html` | 全文言の一覧表（推敲・英訳の元データ） |
| `assets/mockup.css` | 中立なたたき台スタイル（再デザイン前提） |

## 約束ごと

- HTML/CSS は **中立なたたき台**。色・余白・タイポ・ロゴ・イラストは自由に変えてよい。
- 確定しているのは **画面順序・項目・コピー文言・表示条件**（`install.php` 準拠）。
- `📝 デザインメモ`（紫の枠）は **制作者向けの注釈**で、UI には出さない。
- `?` アイコンはツールチップ（ホバー）。中身は `99-copy-deck.html` にも転記済み。

出典: 実装 `public_html/install.php`。NeNe Invoice / MIT。
