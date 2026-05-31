# NeNe Invoice — セキュリティ診断レポート

- **対象**: NeNe Invoice API（自己ホスト型 見積・請求・入金管理 / NENE2 PHP 8.4 + MySQL）
- **実施日**: 2026-05-31
- **手法**: 認可されたブラックボックス + グレーボックス手動診断（自己所有アプリ・ローカル Docker）
- **対象環境**: ローカル Docker（`app` = php:8.4-apache, `db` = mysql:8.0）。`http://localhost:8590`、MySQL `3385`
- **テナント構成**: `TENANT_RESOLUTION=single`, `ORG_SLUG=org-a`（解決 org = org-a/id 1）
- **シードデータ**: org-a(1) / org-b(2)、ユーザ admin/member/viewer/superadmin、各 org に client・invoice・company_settings

> ⚠️ 本診断は**自分のアプリ・隔離されたローカル環境**に対する正当なセキュリティ検証です。破壊的操作（DB ドロップ等）は実行せず、到達性・分離・検証ロジックの確認に留めています。

---

## エグゼクティブサマリ

アプリケーション層（認証・**マルチテナント分離**・認可・入力検証・インジェクション耐性・業務ロジック）は**非常に堅牢**で、試行した攻撃はすべて防御されました。特に組織間のデータ分離は SQL レベルで一貫して強制されており、IDOR / クロステナント漏洩は確認できませんでした。

一方、**デプロイ/運用面**で対処すべき項目が 1 件（High）、認証ハードニングで 1 件（Medium）、情報開示等の軽微な項目が数件あります。

| # | 深刻度 | 項目 | 状態 |
|---|--------|------|------|
| F-1 | **High（条件付き）** | `install.php` が Web ルートに到達可能（マーカー消失時に無認証セットアップ可能） | 要対処 |
| F-2 | **Medium** | `/auth/login` にレート制限・アカウントロックアウトなし（総当たり可能） | 要対処 |
| F-3 | Low / Info | バージョン情報の開示（`Server` / `X-Powered-By`） | 推奨 |
| F-4 | Low / Info | 対称鍵 JWT・単一シークレットで人/サービス両トークンに署名・失効不可 | 設計上の留意 |
| F-5 | Info | HSTS ヘッダなし（TLS 終端構成依存） | 推奨 |
| F-6 | Info | Problem Details の `type` ベース host 不一致（`nene2.dev` 混在） | 軽微 |

---

## 検出された問題（Findings）

### F-1 [High（条件付き）] `install.php` が Web ルートに到達可能

**状況**: `public_html/install.php`（13KB の Web インストーラ）がデプロイ後も配信され、`GET /install.php` が **HTTP 200** でセットアップ画面を返す。

```
GET /install.php  -> HTTP 200（DB設定フォームを表示）
<b>Warning</b>: mkdir(): File exists in <b>/var/www/html/public_html/install.php</b> on line <b>53</b>
```

**分析**:
- インストーラは `var/.installed` マーカーの存在で再実行を拒否する設計（マーカーあり → 「インストール済みです。install.php を削除してください。」で `exit`）。
- しかし防御は **マーカーファイル + 手動削除の案内のみ**。マーカーが**不在**の場合（DB を直接シード/リストアしたデプロイ、コンテナ再デプロイで `var/` が ephemeral かつ DB は永続、git ベース配備でインストーラ未実行など）、インストーラは「未インストール」と判断し**起動状態**になる。
- 起動状態では、無認証の攻撃者が:
  - **step=1**: `file_put_contents(ENV_FILE, …)` で `.env` を上書き → DB 接続先を攻撃者の DB に向ける（= 認証バイパス／全データ差し替え）。
  - **step=2**: 任意の組織・**管理者ユーザを新規作成**（`INSERT INTO users … role=admin`）。
  → **完全乗っ取り**に至る。
- 併せて、`install.php` 単体で **PHP warning が表示**され（display_errors が有効）、絶対パス `/var/www/html/public_html/install.php` を開示。

**影響**: マーカー不在のデプロイ条件下で、無認証の `.env` 改ざん + 管理者作成によるフルテイクオーバー。マーカーは `var/`（多くの構成で ephemeral/gitignore）に置かれるため、DB が永続するコンテナ再デプロイで**容易に再露出**しうる。

**推奨**:
1. **配備物の Web ルートからインストーラを除外**（リリース ZIP に含めない／別ディレクトリ）。または初回起動後に**自動削除**。
2. マーカーに依存せず、**DB に既存 org/user が存在する場合はセットアップを拒否**する二重ガードを追加。
3. インストーラの `.env` 書き込み・管理者作成を、ワンタイムトークン or ループバック限定にする。
4. `display_errors=Off` をインストーラにも適用（パス開示の抑止）。

---

### F-2 [Medium] `/auth/login` にレート制限・アカウントロックアウトなし

**状況**: 同一アカウントへ連続して誤パスワードでログインしても制限・遅延・ロックが一切かからない。

```
10連続 失敗ログイン -> 401 401 401 401 401 401 401 401 401 401
直後に正パスワード   -> HTTP 200（ロックされていない）
```

`src/Auth/LoginHandler.php`（48行）は `InvalidCredentialsException` を 401 に変換するのみで、試行回数の記録・スロットリング・CAPTCHA・指数バックオフ等は未実装。

**影響**: オンライン総当たり / クレデンシャルスタッフィングが可能。`password_hash`（bcrypt cost 12）でハッシュ自体は強いが、弱いパスワードは時間をかければ突破されうる。

**推奨**: IP / アカウント単位のレート制限（例: 5 回/分でバックオフ、N 回でロック or 一時遅延）、失敗試行の監査ログ記録、必要に応じ CAPTCHA。ミドルウェア（`/auth/login` 前段）での実装が望ましい。

**補足（良好点）**: ユーザ列挙は不可。存在しないユーザも誤パスワードも一様に `invalid-credentials` を返す。

---

### F-3 [Low / Info] バージョン情報の開示

```
Server: Apache/2.4.67 (Debian)
X-Powered-By: PHP/8.4.21
```

既知脆弱性の標的選定を容易にする。`ServerTokens Prod` / `ServerSignature Off`、`expose_php=Off` で抑止を推奨。

### F-4 [Low / Info] 対称鍵 JWT / 単一シークレット / 失効不可

- 人間トークンとサービストークン（`/api/*`）が**同一の `NENE2_LOCAL_JWT_SECRET`（HMAC-SHA256）**で署名される。
- 検証で署名鍵を知っていれば任意クレームのトークンを生成可能 — 本診断でも、鍵を用いて `org=2, scopes=[…]` のサービストークンを偽造し `/api/invoices` が 200 を返すことを確認（= 鍵漏洩時はクロス組織のサービスアクセスを含むフル侵害）。
- `jti`/失効リスト/ローテーションがなく、ログアウト/失効 API もない（ステートレス）。盗まれたトークンは `exp`（1 時間）まで有効で取り消せない。

**推奨**: シークレットの厳格な管理（F-1 の `.env` 露出と複合するとクリティカル）、人/サービスで鍵分離 or 鍵 ID(`kid`) によるローテーション、機微操作向けの短い有効期限、必要に応じ失効リスト。

### F-5 [Info] HSTS ヘッダなし
`Strict-Transport-Security` 不在。TLS をリバースプロキシで終端する構成なら、そのプロキシ側で付与を推奨。

### F-6 [Info] Problem Details `type` の host 不一致
404 応答が `https://nene2.dev/problems/not-found`（フレームワーク既定）を返し、アプリの `https://nene-invoice.dev/problems/...` と混在。情報漏洩ではないが一貫性のため統一を推奨。

---

## 検証済み（堅牢と確認できた防御）

### マルチテナント分離（ADR 0006）— **完全**
SQL レベルで `organization_id` が強制され、組織間アクセスはすべて遮断:

| 攻撃 | 結果 |
|---|---|
| admin-a が org-B の `GET /admin/invoices/2` | **404** `invoice-not-found` |
| admin-a が org-B の `GET /admin/clients/2` | **404** |
| admin-a が org-B の `GET /admin/users/3` / `DELETE` | **404** |
| admin-a が org-B の `GET /admin/invoices/2/payments` | **404** |
| 一覧（clients / invoices） | 自組織のみ返却（各 1 件） |
| admin-B のトークンで org-a アプリへ | **403** `organization-mismatch`（OrgGuard） |
| client 作成時に `organization_id:2` / `id:999` を注入 | **org=1 に強制**（mass-assignment 無効、DB 確認済み） |

### 認証（JWT）— **堅牢**
| 攻撃 | 結果 |
|---|---|
| トークンなし / 不正文字列 | **401** |
| 署名改ざん | **401** |
| `alg=none` で superadmin 偽造 | **401** |
| 誤シークレット署名 | **401** |
| 有効署名だが期限切れ | **401** |

### 認可 / 権限昇格 — **堅牢**
- member → `POST /admin/users` **403**、`GET /admin/audit-logs` **403**
- viewer → `POST /admin/clients`（書込）**403**（read は 200）
- admin → `GET /admin/organizations` **403**（superadmin 専用）、superadmin → **200**
- admin が `role=superadmin` のユーザ作成 → **403/422** `role-not-assignable`
- 自分自身の削除 → **409** `cannot-delete-self`
- ロールモデル（member=請求オペレータ／viewer=読取専用）が一貫して強制

### サービス API（`/api/*`）— **堅牢**
- 人間トークン（scopes なし）→ **403** `insufficient-scope`
- トークンなし → **401**

### インジェクション — **堅牢**（全て PDO プリペアド）
- ログイン email に `' OR '1'='1` → **401**（バイパスなし）
- client 名に `'; DROP TABLE users;--` → 文字列として保存、`users` テーブル健在（DROP 不発）
- `?limit` への UNION 注入 → int キャストで無効化

### 入力検証 / 業務ロジック — **堅牢**
- 負の入金額 → **422** `validation-failed`
- 残高超過入金 → **422** `payment-exceeds-outstanding`
- 整数オーバーフロー額 → **422**（クラッシュなし）
- 発行済み請求書の再発行 → **422**
- 深いネスト JSON（5000 段）→ **422**（json_decode depth で安全に拒否）

### エラーハンドリング / ヘッダ / その他 — **良好**
- `APP_DEBUG=false`：不正 JSON・404・型不一致いずれもクリーンな Problem Details、**スタックトレース/パス漏洩なし**（インストーラを除く）
- セキュリティヘッダ: `Content-Security-Policy: default-src 'self'` / `X-Frame-Options: SAMEORIGIN` / `X-Content-Type-Options: nosniff` / `Referrer-Policy` / `Permissions-Policy`
- CORS: 不正 Origin を反映しない（`Access-Control-Allow-Origin` 非出力）
- 公開ダウンロードトークン: 32 byte 乱数・SHA-256 ハッシュ保管・推測不可、未定義トークンは **404**、クロス組織の発行は **404**
- HTTP メソッド: 未定義メソッドは **405**、`TRACE` **405**（XST 不可）、`.env`/`.git`/`composer.json` は **404**

---

## 再現環境（参考）

```
docs/security/harness/
├── Dockerfile           # php:8.4-apache + pdo_mysql + rewrite
├── docker-compose.yml   # app:8590 / db:3385（既存コンテナと非衝突）
├── .env.app.example     # mysql 接続 + ORG_SLUG=org-a の雛形（実体は .gitignore）
└── seed.sql             # 2 組織・5 ユーザ・client/invoice/company_settings
```

起動: `cd docs/security/harness && docker compose up -d --build` ／ 解体: `docker compose -p nene-invoice-sectest down -v`

> 実シークレット（.env.app）・取得トークンは .gitignore 済み（非コミット）。コミットされる値はテスト専用の使い捨て。

---

## 結論と優先対応

アプリケーションのコア（テナント分離・認証認可・検証・インジェクション耐性）は**設計通り堅牢**で、既存のユニット/E2E テスト群と整合する高い品質。残課題は主に**デプロイ/運用の固め**:

1. **最優先 F-1**: インストーラを配備 Web ルートから除外 or 自動削除し、DB 既存データによる二重ガードを追加。
2. **次点 F-2**: ログインのレート制限/ロックアウトを実装。
3. **推奨 F-3〜F-6**: バージョン秘匿、JWT 鍵運用・有効期限見直し、HSTS、Problem Details host 統一。
