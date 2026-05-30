# NeNe Invoice — セキュリティ診断レポート（第2ラウンド / 深掘り）

- **対象**: NeNe Invoice API（NENE2 PHP 8.4 + MySQL）
- **実施日**: 2026-05-31
- **位置づけ**: 第1ラウンド（`security-assessment-2026-05-31.md`）で OWASP 主要カテゴリを網羅。本ラウンドは**より深く・創造的な攻撃面**（JWT 検証の内部挙動・型混同・業務/会計ロジック・サービストークン・PDF・パス細工・状態管理）を狙い、コード解析と実地攻撃を併用。
- **環境**: 同一ローカル Docker（`http://localhost:18090`、MySQL `13309`、org-a/org-b 2 組織シード）

> ⚠️ 認可された自己所有アプリ・隔離環境での検証。破壊的操作は行わず、到達性・分離・ロジックの確認に限定。

---

## エグゼクティブサマリ

第1ラウンドで確認した**コア防御（テナント分離・認証・認可・インジェクション耐性・価格改ざん）は深掘りでも一切破れず**、サービストークンのテナント/スコープ分離、PDF のクロス組織遮断・出力エスケープ、パス細工耐性なども堅牢でした。

一方、**状態管理と会計ロジックの整合性**に新たな指摘が複数見つかりました（直接の権限奪取には至らないが、会計データの正確性・アカウント無効化の実効性に関わる）。

| # | 深刻度 | 項目 |
|---|--------|------|
| R2-1 | **Medium** | ログインが利用者 `status` を無視 → 無効化/招待中ユーザもログイン・操作可能 |
| R2-2 | **Medium** | 承認済み見積を**何度でも請求書に変換可能** → 請求書の重複生成（会計整合性） |
| R2-3 | Low–Medium | 管理API の入金記録が**冪等でない**（`idempotency_key` を無視）→ 再送で二重計上 |
| R2-4 | Low–Medium | 不正な `paid_at` で **HTTP 500**（未処理例外 / 入力検証漏れ。トレース漏洩はなし） |
| R2-5 | Low | 入金日 `paid_at` の**バックデート/未来日**が無制限（1990/2999 を受理） |
| R2-6 | Info / Hardening | JWT verifier: `exp` 欠落/非整数で**無期限化**、OrgGuard が `org=null` を role 無確認で superadmin 扱い（いずれも secret 依存） |
| R2-7 | Info | API が生 HTML を保存・JSON でそのまま返却（PDF/React は出力時にエスケープ済 → 現状悪用不可） |

---

## 検出された問題（Findings）

### R2-1 [Medium] ログインが利用者 `status` を検証しない

`status` を `disabled` / `invited` に設定したユーザでもログインが成功し、発行トークンが**実際に通る**ことを確認:

```
disabled@a.test でログイン -> token 発行
  そのトークンで GET /admin/me       -> 200
  そのトークンで GET /admin/invoices -> 200   ← 無効化が無意味
invited@a.test  でログイン -> token 発行（同様）
```

`src/Auth/LoginUseCase.php` は資格情報（`password_verify`）のみ検証し、`status === 'active'` のチェックがない。加えて JWT はステートレスのため、**無効化操作は既発行トークンを失効させない**（`exp`=1h まで有効）。

**影響**: 退職者・停止アカウント・未アクティベートの招待ユーザが認証・操作を継続できる。
**推奨**: ログイン時に `status === 'active'` を必須化（非アクティブは `invalid-credentials` 相当で拒否）。重要操作では DB の現行ステータス/失効リストを参照する設計を検討。

### R2-2 [Medium] 承認済み見積を複数回 請求書化できる（重複請求書）

同一見積を 3 回変換すると **3 件の請求書が生成**され、見積は `accepted` のまま再変換可能:

```
POST /admin/quotes/1/convert ×3 -> 201 / 201 / 201
invoices(quote_id=1): id 6,7,8（いずれも draft, total 5500）
quotes(1).status = accepted（変換後も不変）
```

発行（issue）には「発行済みは再発行不可（422）」のガードがあるのに対し、**変換（convert）には重複防止ガードがない**。生成物は draft（未採番）だが、二重に issue されれば**重複請求・採番浪費・会計記録の重複**に直結する。会計コンプライアンス（`docs/explanation/accounting-compliance.md`）を重視する本製品では要対処。

**推奨**: 変換は 1 回限りにする（変換成立で見積を `converted` 等の終端状態へ遷移、または `quote_id` に紐づく invoice 存在時は再変換を拒否）。

### R2-3 [Low–Medium] 管理API の入金記録が冪等でない

`src/Payment/RecordPaymentUseCase.php` は `idempotencyKey` による重複排除を実装しているが、**管理ハンドラ `RecordPaymentHandler` は body の `idempotency_key` を読まずに `RecordPaymentInput(...)` を生成**（第5引数 null 既定）。一方サービスAPI `RecordServicePaymentHandler` は `idempotency_key` を必須にしている。

```
admin: POST /admin/invoices/1/payments {amount_cents:500, idempotency_key:"same"} ×3
  -> payments 3 → 6（+3 すべて作成、DB の idempotency_key は全て NULL）
```

**影響**: ネットワーク再送・二重送信で**入金が二重計上**され、請求書の入金状態・会計残高が破綻しうる（フロントの確認ダイアログは緩和に留まる）。
**推奨**: 管理ハンドラでも `idempotency_key`（または `Idempotency-Key` ヘッダ）を受理し UseCase の重複排除へ渡す。

### R2-4 [Low–Medium] 不正な `paid_at` で HTTP 500

```
POST /admin/invoices/1/payments {amount_cents:100, paid_at:"not-a-date"} -> 500
```

不正日付文字列が検証されずに処理され、未処理例外として 500 になる（応答本文はクリーンな Problem Details で**スタックトレース/パスの漏洩はなし**＝ APP_DEBUG=false は適切に機能）。検証漏れ・堅牢性の問題。
**推奨**: `paid_at` を入力検証（ISO 日付パース失敗は 422 validation-failed）。

### R2-5 [Low] 入金日のバックデート/未来日が無制限

```
paid_at = 1990-01-01 -> 201（受理）
paid_at = 2999-12-31 -> 201（受理）
```

過去・未来の任意日付を受理。正当な遡及記録もあるが、税務/会計の期間整合の観点では妥当範囲のガード（例: 請求書発行日〜現在＋猶予）を推奨。

### R2-6 [Info / Hardening] JWT verifier の堅牢性（secret 依存）

HMAC シークレットを用いた検証で以下を確認（いずれも**鍵を知らないと悪用不可**＝第1ラウンド F-4 を増幅する多層防御課題）:

| 細工（正しい署名）| 結果 | 含意 |
|---|---|---|
| `exp` クレーム無し | **200** | `exp` 不在時は期限チェックをスキップ → **無期限トークン** |
| `exp` が文字列/浮動小数 | **200** | `is_int($exp)` 条件のため非整数 exp は期限チェックを回避 |
| `org:null` + `role:admin` | **200** | OrgGuard は `org===null` を**役割無確認で superadmin 扱い**し org チェックを免除 |
| `org:"1"`（文字列）| **403** | `is_int` で正しく拒否（堅牢） |
| `alg:"NONE"` / `"hs256"` | **401** | 大小/別名は厳格拒否（堅牢） |

**推奨**: verifier は `exp` を**必須かつ整数**として検証（欠落/非整数は無効扱い）。OrgGuard の superadmin 免除は `org===null` だけでなく `role==='superadmin'` も確認。鍵は人間/サービスで分離・ローテーション可能に（F-4）。

### R2-7 [Info] API が生 HTML を保存・返却

`name = "<script>alert(1)</script>"` が**生のまま保存・JSON 応答にも生で出現**。ただし:
- **PDF 出力**: 生成 PDF に生の `<script>` / `<b>` は**含まれず（エスケープ済）**を確認（`application/pdf`, 31KB）。
- フロント（React）は描画時に自動エスケープ。

→ 現行の描画経路では悪用不可。JSON API として「保存は生・出力でエスケープ」は許容方針だが、**将来 CSV/Excel エクスポートや別レンダラを追加する際は数式/HTML インジェクションに注意**（`=cmd|...` 等）。

---

## 第2ラウンドで再確認した堅牢性

| 攻撃面 | 結果 |
|---|---|
| サービストークンのテナント分離 | read:invoices(org1) → `/api/invoices/2` **404**、`/api/clients/2` **404** |
| サービストークンのスコープ | read のみで `POST payment`（write必要）**403**、空/ワイルドカード scope **403** |
| PDF クロス組織 | admin-a → `/admin/invoices/2/pdf` **404**、自org **200** |
| PDF 出力エスケープ | `<script>`/`<b>` を生で含まず（HTML インジェクション不可） |
| パス/ルート細工 | 大文字 **404** / 末尾スラッシュ **404** / `..`・二重符号化 **404/403** / DL token トラバーサル **404** |
| `X-Original-URL` で capability 迂回 | **403**（実パスで判定、迂回不可） |
| 価格改ざん | クライアント `total_cents` 無視・サーバ再計算（110000）、税率 5000bps **422**、負数量/単価 **422** |
| 他orgの client 参照で請求書作成 | **422** |
| ページング | `limit` は **100 に丸め**、負/0/巨大値も安全、HPP（`?limit=1&limit=999999`）→ 100 |
| 重複 JSON キー | 安全（422） |
| 型ジャグリング | email/password/name を配列/真偽/オブジェクトに → **422** |
| DoS 耐性 | 100K 文字入力・長大 registration_number → 401/422（ReDoS/ハングなし） |
| エラー処理 | 500 でも Problem Details のみ・**トレース/パス漏洩なし**（APP_DEBUG=false） |

---

## 総括

第2ラウンドの深掘りでも、**機密性に関わるコア防御（テナント分離・認証・認可・インジェクション・価格改ざん）は突破不能**であることを再確認しました。新規指摘は主に **(a) アカウント無効化の実効性（R2-1）** と **(b) 会計データの整合性（R2-2 二重変換 / R2-3 入金冪等性 / R2-4,5 日付検証）** に集約されます。会計コンプライアンスを最重要とする本製品の性格上、**R2-1・R2-2 を優先**し、R2-3〜R2-5、ハードニングの R2-6 を順次対処することを推奨します。

### 優先度順 対応サマリ（第1+第2ラウンド統合）
1. **High** F-1: インストーラの Web ルート露出（配備除外/自動削除＋DB 既存データガード）
2. **Medium** R2-1: ログイン `status` 検証 / R2-2: 見積二重変換防止 / F-2: ログインのレート制限
3. **Low–Medium** R2-3: 入金冪等性 / R2-4: `paid_at` 検証（500→422） / R2-5: 日付範囲ガード
4. **Info/Hardening** R2-6: JWT verifier（exp 必須・OrgGuard role 確認）/ F-3〜F-6: バージョン秘匿・鍵運用・HSTS 等

---

## 対応状況（Remediation, 2026-05-31）

診断で確認したアプリ層の指摘はすべて修正 PR を作成・マージ済み（main）。

| 指摘 | 深刻度 | 状態 | PR |
|---|---|---|---|
| F-1 install.php 露出 | High | ✅ 修正（DB 既存データガード追加） | #186 |
| F-2 ログイン レート制限なし | Medium | ✅ 修正（IP 単位スロットル・429/Retry-After） | #190 |
| R2-1 ログイン status 無視 | Medium | ✅ 修正（active 必須化） | #178 |
| R2-2 見積の二重変換 | Medium | ✅ 修正（1見積=1請求書ガード） | #180 |
| R2-3 入金の冪等性欠如 | Low–Med | ✅ 修正（admin で idempotency_key 受理） | #182 |
| R2-4 不正 paid_at → 500 | Low–Med | ✅ 修正（422 検証） | #182 |
| R2-5 paid_at 未来日 | Low | ✅ 修正（未来日拒否） | #182 |
| R2-6 OrgGuard null-org | Info/Hard | ✅ 修正（superadmin role 必須化） | #184 |
| F-3 バージョン開示 | Low | ✅ 修正（X-Powered-By 除去） | #188 |
| F-5 HSTS | Info | ✅ 部分対応（.htaccess best-effort、TLS 終端は proxy 責務） | #188 |

### フレームワーク/デプロイ層（アプリ修正対象外・文書化のみ）
- **F-4 / R2-6（JWT verifier）**: `exp` 必須化・人/サービス鍵分離・失効リストは vendor (nene2 `LocalBearerTokenVerifier`) とインフラの責務。OrgGuard 側の多層防御は #184 で強化済み。
- **F-6 Problem Details host**: 404 の `nene2.dev` はフレームワーク既定（vendor）。
- **R2-7 生 HTML 保存**: PDF/React とも出力時にエスケープ済で悪用不可（現状変更不要）。
- **Server ヘッダ秘匿・TLS HSTS**: web サーバ/リバースプロキシ設定。
