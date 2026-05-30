# セキュリティ診断 再現ハーネス

実アプリ（PHP 8.4 + MySQL）をローカル Docker で起動し、セキュリティ検証を行うための最小構成。
2 組織・複数ロールのユーザをシードし、テナント分離・認可・認証・業務ロジックを実地で叩けます。

> ⚠️ 認可された自己所有アプリ・隔離環境での検証専用。シークレット類は使い捨て。

## 構成

| ファイル | 役割 |
| --- | --- |
| `Dockerfile` | `php:8.4-apache` + `pdo_mysql` + rewrite、docroot=`public_html` |
| `docker-compose.yml` | `app`(:18090) と `db`(:13309)。リポジトリをマウントし `.env.app` を上書き |
| `seed.sql` | 2 組織 / admin・member・viewer・superadmin / client・invoice・company_settings |
| `.env.app.example` | アプリ設定の雛形（**JWT シークレットは要生成**） |
| `mint.php` | HMAC 署名の JWT 生成（`php mint.php '<payload-json>' '<secret>'`） |

シードユーザのパスワードはいずれも `Passw0rd!23`（テスト専用）。

## 起動

```bash
cp .env.app.example .env.app
php -r "echo bin2hex(random_bytes(32));"   # → .env.app の NENE2_LOCAL_JWT_SECRET に設定
docker compose up -d --build
curl -s localhost:18090/health
```

## 例: ログインしてトークン取得

```bash
curl -s -X POST localhost:18090/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin-a@a.test","password":"Passw0rd!23"}'
```

## 解体

```bash
docker compose -p nene-invoice-sectest down -v
```

## 注意

- `docker-compose.yml` / `seed.sql` 内の DB パスワード等は**ローカル使い捨て**。本番値ではありません。
- `.env.app`（実シークレット）・`tokens.env`・`*.log` は `.gitignore` 済み。
- ポート 18090 / 13309 は診断時の空きポート。衝突する場合は compose を調整してください。
