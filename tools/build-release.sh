#!/usr/bin/env bash
# NeNe Invoice — Tier A リリース ZIP ビルドスクリプト（#576 で堅牢化）
#
# 使い方: bash tools/build-release.sh [version]
# 例:     bash tools/build-release.sh 1.0.0
# 出力:   dist/nene-invoice-<version>.zip ＋ dist/nene-invoice-<version>.zip.sha256
#
# 設計（nene-records の build-release.sh に倣う・#576 / _work/reports/2026-07-05/）:
# - 作業ツリーの vendor は一切触らない。配布物は staging で Packagist の
#   hideyukimori/nene2 ^1.6 を解決した実体 vendor を組む（path repo / symlink を
#   持ち込まない）。symlink が 1 本でも残ったら fail。
# - 同梱は allowlist 方式（dev 専用物・秘密・巨大物の混入を構造的に防ぐ）。
# - 出荷物は WordPress 式にトップレベル展開。SHA-256 サイドカーを併せて出力。

set -euo pipefail

VERSION="${1:-dev}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/build/stage"
ZIP_NAME="nene-invoice-${VERSION}.zip"
# demo ブランチ（feat/demo-disposable-org）は GuardedJwtSecretResolver 等を含む
# nene2 ^1.8.2 系を要求する（composer.json の require と一致させる）。旧値 ^1.6 は
# 当該クラスを含まず、fail-close JWT が壊れるため是正（#576 追補）。
NENE2_CONSTRAINT="^1.8.2"

echo "=== NeNe Invoice Tier A release build: $VERSION ==="

rm -rf "$DIST/build"
mkdir -p "$STAGE"

# ----------------------------------------
# 1. フロントエンド ビルド（Vite → public_html/admin へ出力）
# ----------------------------------------
echo "→ frontend build..."
(cd "$ROOT/frontend" && npm ci --silent && npm run build)

# ----------------------------------------
# 2. アプリ本体（allowlist コピー）
# ----------------------------------------
echo "→ copying application files (allowlist)..."
cp -r "$ROOT/src" "$STAGE/src"
cp -r "$ROOT/database" "$STAGE/database"
# public_html には built SPA（admin/）・install.php・installer.js・index.php・
# openapi.php が含まれる。フロントビルド後にコピーすること。
cp -r "$ROOT/public_html" "$STAGE/public_html"
# resources/fonts の IPAex TTF（ipaexg.ttf / ipaexm.ttf）は mPDF が PDF 描画時に
# FS 読みする。欠けると請求書/見積 PDF が 500 になる（#550）。
cp -r "$ROOT/resources" "$STAGE/resources"
cp "$ROOT/.env.example" "$STAGE/.env.example"
cp "$ROOT/phinx.php" "$STAGE/phinx.php"
# 本番 cron ツール（vendor/autoload.php をプロジェクト直下から require する）。
# sweep-demo.php = 使い捨てデモ org の掃除、run-recurring.php = 定期請求の実行。
# dev/ビルド専用ツール（build-release.sh / seed-dev.php 等）は同梱しない。
mkdir -p "$STAGE/tools"
cp "$ROOT/tools/sweep-demo.php" "$STAGE/tools/sweep-demo.php"
cp "$ROOT/tools/run-recurring.php" "$STAGE/tools/run-recurring.php"
cp "$ROOT/composer.json" "$STAGE/composer.json"
cp "$ROOT/README.md" "$STAGE/README.md"

# var/（空・書き込み先）
mkdir -p "$STAGE/var"
touch "$STAGE/var/.gitkeep"

# ----------------------------------------
# 3. Packagist ^1.6 の本番 vendor（path repo / symlink を持ち込まない）
# ----------------------------------------
echo "→ composer: resolve hideyukimori/nene2 ${NENE2_CONSTRAINT} from Packagist..."
php -r '
$path = $argv[1];
$json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$json["require"]["hideyukimori/nene2"] = $argv[2];
unset($json["repositories"]);
file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
' "$STAGE/composer.json" "$NENE2_CONSTRAINT"
(cd "$STAGE" && composer update --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader --quiet)

NENE2_RESOLVED="$(cd "$STAGE" && composer show hideyukimori/nene2 2>/dev/null | awk '/^versions/ {print $NF}')"
echo "  nene2 resolved: ${NENE2_RESOLVED}"

# ----------------------------------------
# 4. 出荷前検査 — symlink ゼロ・nene2 Install ツールキット実体
# ----------------------------------------
if [ "$(find "$STAGE" -type l | wc -l)" -ne 0 ]; then
    echo "✗ 配布物に symlink が混入しています:" >&2
    find "$STAGE" -type l >&2
    exit 1
fi

if [ ! -f "$STAGE/vendor/hideyukimori/nene2/src/Install/PayloadInstaller.php" ]; then
    echo "✗ vendor/hideyukimori/nene2 が実体として解決されていません。" >&2
    exit 1
fi

# ----------------------------------------
# 5. ZIP（内容物をトップレベルに = WordPress 式）＋ SHA-256 サイドカー
# ----------------------------------------
echo "→ zip: dist/${ZIP_NAME}..."
mkdir -p "$DIST"
rm -f "$DIST/$ZIP_NAME" "$DIST/$ZIP_NAME.sha256"
(cd "$STAGE" && zip -qr "$DIST/$ZIP_NAME" . -x "*.DS_Store" -x "__MACOSX/*" -x "*/node_modules/*")

echo "→ sha256 サイドカー生成..."
(cd "$DIST" && sha256sum "$ZIP_NAME" > "$ZIP_NAME.sha256")

# ----------------------------------------
# 6. サイズ実測レポート
# ----------------------------------------
RAW_SIZE="$(du -sh "$STAGE" | cut -f1)"
ZIP_SIZE="$(du -sh "$DIST/$ZIP_NAME" | cut -f1)"
FONT_SIZE="$(du -ch "$STAGE/resources/fonts/"*.ttf 2>/dev/null | tail -1 | cut -f1)"
SHA256="$(cut -d' ' -f1 "$DIST/$ZIP_NAME.sha256")"

rm -rf "$DIST/build"

echo ""
echo "✓ ビルド完了: dist/${ZIP_NAME}"
echo "  nene2:        ${NENE2_RESOLVED}"
echo "  raw:          ${RAW_SIZE}"
echo "  zip:          ${ZIP_SIZE}"
echo "  内フォント:   ${FONT_SIZE}（IPAex TTF・軽量化は #548）"
echo "  sha256:       ${SHA256}"
echo "  sidecar:      dist/${ZIP_NAME}.sha256"
