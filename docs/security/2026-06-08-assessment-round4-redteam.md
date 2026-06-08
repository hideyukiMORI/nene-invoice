# NeNe Invoice — レッドチーム ペネトレーションテスト（第4ラウンド / 敵対的・実弾）

- **対象**: 稼働中インスタンス `http://localhost:8510`（実DB・実アプリ、スタブなし）
- **実施日**: 2026-06-08
- **位置づけ**: レビューではなく**能動的攻撃**。9モジュールの実リクエスト＋DB直接検証＋MySQL再現。クラッカー視点で「確実に壊す」前提。全テスト痕跡は清掃済み。

> ⚠️ 認可された自己所有アプリ・隔離環境での検証。

---

## エグゼクティブサマリ

**本気で壊しに行ったが、機密性・完全性・認可の防御は破れなかった。** 認証バイパス・テナント越境・データ漏洩・インジェクション・権限昇格・会計整合性の破壊は**いずれも不成立**。発見できたのは入力検証の軽微な綻び2件と、情報/精度の指摘2件のみ。**全件修正・文書化済み**。

| # | 深刻度 | 項目 | 対応 |
|---|--------|------|------|
| F1 | Low | テキスト項目の最大長検証が欠如（20万字 description / 10万字 name を受理） | ✅ 修正（`TextLimit` で 422） |
| F2 | Low | `amount_cents` の float 暗黙切り捨て（`100.5→100` を201受理） | ✅ 修正（`PaymentAmount` で整数厳格化） |
| F3 | Info | Round 3 の CSV 5MB 上限はフレームワーク 413(1MiB) で到達不能（自己訂正） | ✅ 1MiB へ整合・多層防御と明記 |
| F4 | Info | 採番の read-after-write レース | ✅ 文書化（UNIQUE制約で重複なし） |

---

## 発見した綻び（新規・修正済み）

### F1 [Low] テキスト項目の最大長検証が欠如 → 修正
**実証**: item description に20万字、client name に10万字を投入 → **201で受理・保存**（SQLiteは長さ非強制）。MySQL/PG の `VARCHAR(255)/(1024)` strict mode ではオーバーフロー（SQLSTATE 22001）が executor の `isConstraintViolation`（23xxx のみ）で拾われず **HTTP 500**（推論）。SQLite は無制限保存で肥大（実証）。

**修正**: `src/Support/TextLimit.php`（カラム長整合の定数 + 超過時 422）を新設し、`RequestField::optionalString` に統合。全 write 経路（Client / Item / Template / Company / Quote / Invoice / Payment / Organization / User / LineItem）へ適用。
**再検証（ライブ）**: 20万字→422、256字→422、正常→201、client 10万字→422。

### F2 [Low] `amount_cents` の float 暗黙切り捨て → 修正
`RecordPaymentHandler.php` / ServiceApi の `(int)$amountValue` が `100.5 → 100` を201受理。会計の「integer cents・float禁止」原則に反する。

**修正**: `src/Payment/PaymentAmount.php` で整数 or 純整数文字列のみ受理、float/小数文字列は 422。
**再検証（ライブ）**: `100.5`→422、`"100"`→201、`100`→201。

### F3 [Info] Round 3 L-1 の自己訂正 → 整合
フレームワーク `RequestSizeLimitMiddleware`（既定 **1 MiB**）が body を 413 で先に弾くため、Round 3 で追加した `CsvImport::MAX_BYTES=5MB` は到達不能だった（メモリDoSは元々緩和済み）。`MAX_BYTES` を **1 MiB に整合**し、413 が一次防御である旨をコメントに明記（多層防御として保持）。

### F4 [Info] 採番の read-after-write レース → 文書化
`PdoDocumentSequenceRepository::nextNumber()` は `UPDATE +1` 後に別 `SELECT`。理論上の同時実行競合はあるが、UNIQUE制約 `uniq_invoices_org_number` ＋ issue トランザクションにより**重複番号は発生しない**（衝突時は片方が制約エラー）。設計コメントに明記。

---

## 突破不能を実弾で確認した領域

| 攻撃 | 試行内容 | 結果 |
|---|---|---|
| 権限昇格 | viewer書込 / member user管理 / admin→superadmin / 権限跨ぎ | 全て 403/422 |
| JWT偽造 | payload改ざん・alg:none・署名除去・**既定鍵偽造**・alg大小・不正形式 | 全て 401（M-1修正をライブ実証） |
| 認可回避 | 末尾/・大小・//・/./・%エンコード・X-Original-URL・X-Rewrite-URL・**X-HTTP-Method-Override** | 回避不可（override無視、対象データ無傷） |
| SQLi | search/sort/filter/status に `' OR 1=1`・UNION・DROP・ブラインド | 全てリテラル化、DB無傷、時間差なし |
| mass assignment | `organization_id:2`/`id`/`is_deleted` 注入 | 無視（org強制） |
| 型混同 | 配列/オーバーフロー/負/不正税率 | 全て 422（500なし） |
| 会計ロジック | 過剰入金(直接+累積)・負/0・下書き入金・再発行・二重変換・未承認変換 | 全て 422、冪等性も重複排除 |
| DoS | limit巨大/負・offset巨大・5000段ネスト・5万明細・巨大ボディ | 422/400/413 で防御 |
| 情報漏洩 | ユーザー列挙・エラー冗長・ヘッダ | 列挙不可・CSP/X-Frame-Options有・Server/X-Powered-Off |
| CSV数式注入 | `=cmd`/`@SUM`/`+`/`-`/`=HYPERLINK` を取込→エクスポート | エクスポートで `'` 無害化 |
| DLトークン | 改ざん/トラバーサル/総当たり/空 | 全て 404、正常系のみ200/PDF |
| Content-Type混同 | form-encoded/XML を JSON 口へ | 400（厳格JSON） |

---

## 対応状況（Remediation, 2026-06-08）

| 指摘 | 深刻度 | 状態 | Issue |
|---|---|---|---|
| F1 最大長検証 | Low | ✅ 修正 | #403 |
| F2 amount float | Low | ✅ 修正 | #404 |
| F3 CSV上限整合 | Info | ✅ 修正 | #405 |
| F4 採番レース文書化 | Info | ✅ 文書化 | #406 |

総評: 多層防御（認証/認可・org強制スコープ・パラメータ化SQL・出力エスケープ・状態機械・冪等性）は敵対的攻撃に耐え切った。残課題だった防御的入力検証 2件も解消。
