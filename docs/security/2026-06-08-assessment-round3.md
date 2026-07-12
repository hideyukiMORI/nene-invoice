# NeNe Invoice — セキュリティ診断レポート（第3ラウンド / 全機能網羅）

- **対象**: NeNe Invoice バックエンド API（NENE2 PHP 8.4）全ドメイン
- **実施日**: 2026-06-08
- **位置づけ**: 第1・2ラウンド以降に追加された機能（Phase 4 の CSV インポート/エクスポート・一覧の検索/フィルタ/ソート・ServiceApi・PostgreSQL 対応）を含め、**全機能を改めて網羅診断**。コードレベル監査（read-only）＋ライブ検証（`composer audit`・トークン偽造 PoC・未認証プローブ）＋ Round 1–2 修正のリグレッション確認。

> ⚠️ 認可された自己所有アプリ・隔離環境での検証。破壊的操作は行っていない。

---

## エグゼクティブサマリ

**コア防御（テナント分離・SQL インジェクション耐性・認可・暗号・出力エスケープ）は突破不能**であり、Round 1–2 の指摘もすべて修正が維持されている（リグレッションなし）。新規指摘は **Medium 1 件 ＋ Low 3 件 ＋ Info 3 件** で、いずれも権限の直接奪取ではなく「安全でない既定値」と運用堅牢性に関するもの。

| # | 深刻度 | 項目 | 対応 |
|---|--------|------|------|
| M-1 | **Medium** | JWT シークレット未設定時、公開リポジトリ内の固定値へ fail-open | ✅ 修正（本番で起動拒否＝fail-closed） |
| L-1 | Low | CSV インポートが全行を先にメモリ展開（行数上限の前）＋ボディサイズ上限なし | ✅ 修正（解析前にバイト上限） |
| L-2 | Low | ユーザーのパスワード強度ポリシーなし | ✅ 修正（最小12・最大256） |
| L-3 | Low | ログインスロットルが IP 単位のみ（プロキシ配下で全体共有・アカウント単位なし） | 📝 文書化（IP 単位は意図的設計） |
| I-1 | Info | vendor verifier の `exp` 任意（欠落/非整数で無期限） | 📝 文書化（M-1 対応で実害解消方向） |
| I-2 | Info | `MAIL_ENCRYPTION` 空時に SMTP 平文・無認証 | 📝 文書化（運用ガイド） |
| I-3 | Info | CSV 系の `assert()` がファイルハンドル保証に依存（本番で無効化） | 📝 情報（堅牢性のみ） |

---

## 検出された問題（Findings）と対応

### M-1 [Medium] 安全でない既定 JWT シークレットへの fail-open

`src/Auth/AuthServiceProvider.php` は `NENE2_LOCAL_JWT_SECRET` 未設定時、公開リポジトリ内の固定値 `DEFAULT_DEV_SECRET = 'nene-invoice-dev-secret'` で JWT を署名/検証していた。人間トークンとサービストークンの両方で共有される鍵。

**実証**: アプリと同一の `LocalBearerTokenVerifier` に既定値を渡して `{"sub":1,"role":"superadmin","org":null,"exp":<10年後>}` を発行 → 同 verifier が受理（リポジトリ知識のみで認証完全バイパス＋クロステナント）。

**緩和（既存）**: 公式インストーラ（`public_html/install.php`）は `random_bytes(32)` で強力なシークレットを自動生成・`.env` 書込。残存リスクは手動/Docker 構築で未設定のまま運用するケース。

**対応**: `resolveJwtSecret()` を追加し、`APP_ENV=production` でシークレット未設定なら **起動を拒否（fail-closed）**。`local`/`test` のみ開発用フォールバックを許可。`.env.example`・運用ガイドのコメント強化。

### L-1 [Low] CSV インポートのメモリ DoS

`src/Support/CsvImport.php` の `readRecords()` は全レコードを先にメモリ展開し、`maxRows` 上限はその後に判定。`Import{Clients,Items}CsvHandler` は `(string) $request->getBody()` で全ボディを読み込み、アプリ層のサイズ上限がなかった。

**対応**: `CsvImport::parse()` 冒頭で生バイト上限（`MAX_BYTES = 5MB`）を検証し、超過は読込前に 422 で拒否（両インポートが通る単一チョークポイント）。

### L-2 [Low] パスワード強度ポリシー欠如

`CreateUserHandler` / `UpdateUserHandler` は空でないことのみ検証していた。

**対応**: `src/User/PasswordPolicy.php` を追加（最小 12・最大 256・コードポイント数で判定）し、create / update 双方の HTTP 境界で適用。ユースケース層テストは非影響。

### L-3 [Low] ログインスロットルの粒度（設計尊重 → 文書化）

`LoginThrottleInterface` は **意図的に IP 単位**（アカウントロックアウト DoS を避ける設計、ドキュメントに明記）。アカウントロックアウトの追加は当該脅威モデルに反するため採用しない。リバースプロキシ配下で `REMOTE_ADDR` がプロキシ IP になる点は運用設定の問題。

**対応**: 運用ガイドに「実クライアント IP 解決（信頼できるプロキシの `X-Forwarded-For`）」を明記。

### I-1〜I-3 [Info]
- **I-1**: vendor `LocalBearerTokenVerifier` は `exp` 欠落/非整数で期限チェックをスキップ。アプリ発行トークンは常に整数 `exp` を付与。M-1 対応で鍵推測による偽造経路が塞がり実害は解消方向（vendor 責務）。
- **I-2**: `MAIL_ENCRYPTION` 空で SMTP 平文・無認証（ローカル Mailpit 用）。運用ガイドに本番 TLS 必須を明記。
- **I-3**: CSV 系で `assert($handle !== false)`。本番（`zend.assertions=-1`）では無効化されハンドル失敗が静かに進む（堅牢性のみ）。

---

## 検証済みの堅牢性（指摘なし）

| 攻撃面 | 結果 |
|---|---|
| マルチテナント分離 | 全リポジトリで `organization_id` をリクエストスコープ holder から強制。`OrgGuardMiddleware`（superadmin は role 確認付き）。サービストークンは token の org でスコープ。 |
| SQL インジェクション | 全パラメータ化。動的 ORDER BY はホワイトリスト、IN 句はプレースホルダ、監査ログの動的 WHERE も静的カラム名＋束縛。**該当なし** |
| 認可（RBAC） | `CapabilityResolver` はプレフィックス総当たりで未登録ルートも安全側。`ServiceScopeResolver` は全 `/api` 登録ルートをカバー。人間トークン（scopes 無）は `/api` から排除。 |
| 権限昇格 | superadmin は API で割当不可、他組織へのユーザー作成不可。 |
| 暗号 | bcrypt、CSPRNG トークン、SHA-256 ハッシュ保存、`hash_equals`。 |
| 公開 DL トークン | 256bit・ハッシュ保存・期限・org スコープ・ファイル名サニタイズ。 |
| PDF 生成 | 全ユーザー入力エスケープ。`logo_url` は **base64 の `data:` 画像 URI のみ埋め込み**（`PdfLogo`・Issue #510）。http/https・ローカルパス・その他スキームは描画しない＝**サーバ側フェッチなし → SSRF なし**、src は属性エスケープ済で **HTML インジェクションなし**。 |
| CSV エクスポート | 数式インジェクション無害化済み。 |
| メール送信 | PHPMailer API 経由でヘッダインジェクション対策済み。 |
| 危険シンク | `eval`/`exec`/`system`/`unserialize`/動的 `include` **なし**。 |
| 依存パッケージ | `composer audit` → 既知脆弱性 **0 件**。 |
| 構成 | CORS 無効（ワイルドカードなし）、`X-Powered-By` 除去、`.env` gitignore。 |
| ビジネスロジック（R1–R2） | 見積二重変換ガード／入金冪等性／`paid_at` 検証／ログイン `status` 必須 — **全て修正維持**。 |

---

## 対応状況（Remediation, 2026-06-08）

| 指摘 | 深刻度 | 状態 | Issue |
|---|---|---|---|
| M-1 JWT 既定値 fail-open | Medium | ✅ 修正（fail-closed） | #398 |
| L-1 CSV インポート メモリ DoS | Low | ✅ 修正（バイト上限） | #399 |
| L-2 パスワードポリシー | Low | ✅ 修正（最小12） | #400 |
| L-3 / I-1 / I-2 運用ハードニング | Low/Info | 📝 文書化（運用ガイド・.env.example） | #401 |
| I-3 assert 依存 | Info | 情報（変更なし） | — |
