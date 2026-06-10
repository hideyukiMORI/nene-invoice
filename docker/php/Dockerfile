# syntax=docker/dockerfile:1
#
# NeNe Invoice — 開発用アプリイメージ（マルチステージ）
#
# stage 1: Node で管理 UI（React/Vite）をビルドし public_html/admin に出力する。
# stage 2: PHP 8.4 + Apache。ビルド済み SPA を焼き込み、Apache が API と UI を
#          同一オリジンで配信する（ホストに Node 不要で `docker compose up` だけで
#          UI まで到達できる）。FE 開発で HMR が要るときはホスト Vite(5185) を併用。

# ---- stage 1: frontend build -------------------------------------------------
FROM node:22-slim AS frontend-build

WORKDIR /app

# 依存を先に入れてレイヤキャッシュを効かせる。
COPY frontend/package.json frontend/package-lock.json ./frontend/
RUN cd frontend && npm ci

# vite の outDir は ../public_html/admin（base './'）。リポジトリ相対で出力するため
# frontend/ ごとコピーしてからビルドする。
COPY frontend ./frontend
RUN cd frontend && npm run build

# ---- stage 2: php + apache runtime ------------------------------------------
FROM php:8.4-apache AS app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libsqlite3-dev \
       libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_sqlite intl gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && printf '<Directory /var/www/html/public_html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/nene-invoice.conf \
    && a2enconf nene-invoice \
    && sed -ri -e 's!/var/www/html!/var/www/html/public_html!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public_html!g' /etc/apache2/apache2.conf \
    && sed -i 's/^ServerTokens .*/ServerTokens Prod/' /etc/apache2/conf-available/security.conf \
    && sed -i 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && printf 'Header always unset X-Powered-By\n' > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening \
    && printf 'expose_php = Off\ndisplay_errors = Off\nlog_errors = On\n' > /usr/local/etc/php/conf.d/nene-invoice-security.ini

WORKDIR /var/www/html

# ビルド済み SPA を焼き込む。compose では named volume `admin-build` がこのパスを
# 受け継ぐため、ソースのバインドマウントに隠されずに配信される。
COPY --from=frontend-build /app/public_html/admin /var/www/html/public_html/admin
