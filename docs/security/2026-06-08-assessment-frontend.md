# NeNe Invoice — フロントエンド（React SPA）セキュリティ診断

- **対象**: `frontend/`（React 19 + react-router + react-query + react-hook-form + zod、TS/TSX 373ファイル）＋ 静的配信構成
- **実施日**: 2026-06-08
- **手法**: 静的解析（XSSシンク・トークン処理・リダイレクト・秘密情報・配信ヘッダ）＋ `npm audit` ＋ ビルド成果物検査 ＋ Apache配信のヘッダ実検証 ＋ **レッドチーム（Playwright + 実Chromium による敵対的攻撃）**

> ⚠️ 認可された自己所有アプリの検証。

---

## エグゼクティブサマリ

クライアント側の実装（トークンのメモリ保持＋fail-closed、Bearerヘッダ認証、出力エスケープ、ログアウト時キャッシュ消去）は模範的。実所見は**配信ヘッダの1件（Low）**のみで、修正済み。

| # | 深刻度 | 項目 | 対応 |
|---|--------|------|------|
| FE-1 | Low | 静的配信されるSPAシェル/アセットにCSP・X-Frame-Options等が未適用 → クリックジャッキング余地 | ✅ 修正（`.htaccess` に `Header always set`） |

---

## FE-1 [Low] 静的SPAアセットのセキュリティヘッダ欠落 → 修正

`public_html/.htaccess` の書き換えは存在ファイルを除外（`RewriteCond %{REQUEST_FILENAME} !-f`）するため、`admin/index.html` と `admin/assets/*.js|css` は index.php（`SecurityHeadersMiddleware`）を経由せず Apache が静的配信していた。結果、これら静的応答には CSP / X-Frame-Options / X-Content-Type-Options 等が付かず、HSTS のみが適用されていた（管理UIの iframe 化＝クリックジャッキングの余地、HTML文書への多層防御CSP不在）。

**修正**: `.htaccess` の `<IfModule mod_headers.c>` に `Header always set` で以下を追加し、静的アセットにも一律適用。
```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
  img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none';
  base-uri 'self'; form-action 'self'; frame-ancestors 'self'
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), geolocation=(), microphone=()
```
CSP はビルド実態に整合: 外部 module スクリプトのみ（`script-src 'self'`・インラインscript無し）、動的な inline style 属性のため `style-src 'unsafe-inline'`、API は同一オリジン（`connect-src 'self'`）。

**検証（Apache実機）**: `apachectl configtest` = Syntax OK。`admin/index.html` と静的 JS アセットの両方に上記ヘッダが付与されることを `curl -D -` で確認。CSP は SPA を壊さない（外部moduleスクリプト＋inline style属性を許容）。

---

## 安全を確認した領域（所見なし）

| 観点 | 結果 |
|---|---|
| XSSシンク | `dangerouslySetInnerHTML`/`innerHTML`/`eval`/`new Function`/`document.write` 皆無。全描画はJSX自動エスケープ。サーバ `detail` もテキスト描画 |
| トークン保管 | メモリ保持のみ（`localStorage` はi18nロケールのみ）。リロードで失効＝fail-closed |
| CSRF | `Authorization: Bearer`（Cookie不使用）→ 構造的に不成立 |
| 401処理 | トークン消去＋`AuthGate` fail-closed でログイン画面・理由表示 |
| ログアウト | `signOut()` ＋ `queryClient.clear()` でキャッシュ全消去（データ残留なし） |
| オープンリダイレクト | `navigate()`/`<Navigate>` は全てハードコード/アプリ生成パス。クエリ駆動リダイレクト無し |
| ダウンロード | blob URL ＋ `download` 属性 ＋ `revokeObjectURL`、ファイル名はサーバ生成。共有リンクはテキスト＋クリップボード |
| 秘密情報 | ソース内に鍵/トークン無し。`import.meta.env` は公開フラグのみ |
| ビルド | 本番 sourcemap無効、インラインscript無し |
| 依存 | `npm audit` 脆弱性0件 |
| target=_blank / postMessage / iframe | 該当なし |

---

## レッドチーム（実ブラウザ敵対的攻撃, 2026-06-08）

「完全に破壊する」前提で Playwright + 実 Chromium による能動的攻撃を実施。**フロントは突破不能で、新規所見ゼロ**。FE-1 修正（#409）が実際に強制されることも実証。

### 1. Stored XSS 全フィールド注入 — ❌ 不成立
client 名/担当者/住所、item 説明、invoice/quote 備考、template 名、会社 legal_name/住所へ
`<img src=x onerror=…>` / `<script>` / `<svg onload=…>` を API 経由で注入し、各描画ページを
実ブラウザで開いて実行検知。**ペイロードはエスケープされたテキストとして描画**
（`htmlEscaped:true` / `htmlLive:false` / `liveImg:0`、`window.__XSS` 未設定、dialog 不発火）。
React の JSX 自動エスケープが全描画経路で機能（`dangerouslySetInnerHTML` 不在）。

### 2. fail-closed セッション — ✅ 確認
フルリロードでメモリ保持トークンが消え、`AuthGate` がログイン画面へ強制（リロード＝即ログアウト）。

### 3. CSP 強制（Apache 実機）— ✅ 多層防御
注入したインライン script は実行されず（`script-src-elem blocked inline`）、外部攻撃者 script
（`evil.example.com`）もブロック。仮に XSS が存在しても `script-src 'self'` が実行・外部送信を阻止。

### 4. クリックジャッキング（Apache 実機）— ✅ ブロック
クロスオリジン iframe にアプリは描画されず（`frameRenderedApp:false`）。X-Frame-Options /
`frame-ancestors 'self'` が機能（FE-1 修正の実効を確認）。

### 5. その他
オープンリダイレクト不成立（ハードコード遷移のみ）、トークン窃取経路なし（メモリ保持＋XSS不可）、
Bearer ヘッダ認証で CSRF 構造的に不成立、URL/hash DOM-XSS なし、`npm audit` 0 件。

> 検証用に注入したデータ（marker `ZZXSS`）と上書きした会社設定は、テスト後に dev DB を完全復元済み。ソース変更なし。

---

## 対応状況（Remediation, 2026-06-08）

| 指摘 | 深刻度 | 状態 | Issue |
|---|---|---|---|
| FE-1 静的アセットのセキュリティヘッダ | Low | ✅ 修正 | #408 |
| レッドチーム結果の記録（新規所見なし） | — | ✅ 記録 | #410 |
