# syntax=docker/dockerfile:1
#
# NeNe Invoice — インストーラ疑似体験用イメージ（Tier A 共有ホスティング相当）
#
# PHP 8.4 + Apache のみ。アプリのコードと「ビルド済み SPA」は compose のバインド
# マウント（新規展開された dist）から供給するので、ここでは焼き込まない。
# `.htaccess`（AllowOverride All）・mod_rewrite・pdo_mysql・zip など、共有ホスティ
# ングが通常備える前提を再現する（zip は install.php の手動アップロード取得 #544
# が ZipArchive で展開に使う）。
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libicu-dev libsqlite3-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_sqlite intl gd zip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && printf '<Directory /var/www/html/public_html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/nene-invoice.conf \
    && a2enconf nene-invoice \
    && sed -ri -e 's!/var/www/html!/var/www/html/public_html!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public_html!g' /etc/apache2/apache2.conf \
    && printf 'expose_php = Off\ndisplay_errors = Off\nlog_errors = On\n' > /usr/local/etc/php/conf.d/nene-invoice.ini

WORKDIR /var/www/html
