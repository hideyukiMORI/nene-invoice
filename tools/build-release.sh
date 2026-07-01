#!/usr/bin/env bash
# NeNe Invoice — Tier A リリース ZIP ビルドスクリプト
# 使い方: bash tools/build-release.sh [version]
# 例:     bash tools/build-release.sh 1.0.0
# 出力:   dist/nene-invoice-<version>.zip

set -euo pipefail

VERSION="${1:-dev}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
WORK="$DIST/build"
ZIP_NAME="nene-invoice-${VERSION}.zip"

echo "=== NeNe Invoice release build: $VERSION ==="

# ----------------------------------------
# 1. クリーンアップ
# ----------------------------------------
rm -rf "$WORK"
mkdir -p "$WORK"

# ----------------------------------------
# 2. フロントエンド ビルド
# ----------------------------------------
echo "→ フロントエンド ビルド..."
cd "$ROOT/frontend"
npm ci --silent
npm run build
cd "$ROOT"

# ----------------------------------------
# 3. Composer（本番依存のみ）
# ----------------------------------------
echo "→ composer install --no-dev..."
composer install --no-dev --optimize-autoloader --quiet

# ----------------------------------------
# 4. ファイルコピー
# ----------------------------------------
echo "→ ファイルをコピー中..."

# PHP ソースと設定
cp -r "$ROOT/src"                 "$WORK/src"
cp -r "$ROOT/vendor"              "$WORK/vendor"
cp -r "$ROOT/database"            "$WORK/database"
cp -r "$ROOT/public_html"         "$WORK/public_html"
# resources/fonts holds the bundled IPAex fonts that MpdfFactory loads at PDF
# render time (resources/fonts/ipaexg.ttf). Without this the installed app throws
# "Cannot find TTF TrueType font file" and every invoice/quote PDF 500s (#550).
cp -r "$ROOT/resources"           "$WORK/resources"
cp    "$ROOT/.env.example"        "$WORK/.env.example"
cp    "$ROOT/phinx.php"           "$WORK/phinx.php"
cp    "$ROOT/composer.json"       "$WORK/composer.json"

# var/ ディレクトリ（空、書き込み可能にするため）
mkdir -p "$WORK/var"
touch "$WORK/var/.gitkeep"

# ----------------------------------------
# 5. ZIP 作成
# ----------------------------------------
echo "→ ZIP 作成: dist/${ZIP_NAME}..."
mkdir -p "$DIST"
cd "$WORK"
zip -r "$DIST/$ZIP_NAME" . -x "*.DS_Store" -x "__MACOSX/*" -x "*/node_modules/*" -q
cd "$ROOT"

# ----------------------------------------
# 6. composer を dev 依存含めて元に戻す
# ----------------------------------------
echo "→ composer install (dev 依存を復元)..."
composer install --quiet

rm -rf "$WORK"

echo ""
echo "✓ ビルド完了: dist/${ZIP_NAME}"
echo "  サイズ: $(du -sh "$DIST/$ZIP_NAME" | cut -f1)"
