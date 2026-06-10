#!/bin/sh
# NeNe Invoice — app コンテナ起動スクリプト
#
#   composer install → MySQL 待機 → マイグレーション → （任意）dev シード → Apache
#
# ホストのソースがバインドマウントされる前提なので、composer install と
# マイグレーションは毎回実行する（いずれも冪等）。
set -eu

wait_for_mysql() {
  if [ "${DB_ADAPTER:-mysql}" != "mysql" ]; then
    return 0
  fi

  echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
  attempt=0
  while [ "$attempt" -lt 30 ]; do
    if php -r '
      $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        getenv("DB_HOST") ?: "mysql",
        getenv("DB_PORT") ?: "3306",
        getenv("DB_NAME") ?: "nene_invoice",
        getenv("DB_CHARSET") ?: "utf8mb4",
      );
      new PDO($dsn, getenv("DB_USER") ?: "", getenv("DB_PASSWORD") ?: "");
    ' 2>/dev/null; then
      echo "MySQL is ready."
      return 0
    fi
    attempt=$((attempt + 1))
    sleep 2
  done

  echo "MySQL did not become ready in time." >&2
  exit 1
}

composer install --no-interaction --prefer-dist

wait_for_mysql

composer migrations:migrate

# 開発用ダミーデータ（admin@example.com / password123 など）。冪等なので毎回呼んで
# よいが、本番相当の確認をしたいときは INVOICE_SEED_DEV=0 で無効化できる。
if [ "${INVOICE_SEED_DEV:-1}" = "1" ]; then
  php tools/seed-dev.php || echo "seed-dev skipped (non-fatal)." >&2
fi

exec apache2-foreground
