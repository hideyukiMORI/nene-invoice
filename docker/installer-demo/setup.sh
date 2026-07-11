#!/bin/sh
# NeNe Invoice — インストーラ疑似体験の「新規展開」を作る。
#
# リリース ZIP を展開した直後（.env も .installed も無い）状態を再現するため、
# 配布物に含まれるファイル群だけをリポジトリ外の隔離ディレクトリにコピーする。
# こうすることで install.php が書き込む .env / var/.installed が**開発用の repo を
# 汚さない**。
set -eu

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEST="${INSTALLER_DEMO_DIR:-/tmp/nene-installer-demo}"

echo "→ 新規展開を作成: $DEST"
rm -rf "$DEST"
mkdir -p "$DEST/var"

# リリース ZIP（tools/build-release.sh）と同じ構成。
for d in src vendor database public_html resources; do
  cp -r "$ROOT/$d" "$DEST/$d"
done
cp "$ROOT/composer.json" "$ROOT/phinx.php" "$ROOT/.env.example" "$DEST/"

# 念のため新規状態を保証（万一コピー元に紛れていても除去）。
rm -f "$DEST/.env" "$DEST/var/.installed"

# 共有ホスティング相当: Apache(www-data) が .env と var/.installed を書けるよう、
# ルートと var/ を書き込み可能にする（install.php の要件チェックが見るのはここ）。
# public_html も書き込み可能にする — 共有ホスティングでは PHP がファイル所有者
# として走り、完了時の install.php 自己削除（#644・@unlink(__FILE__)）ができる。
# bind mount ではディレクトリ権限が無いと unlink できず、実態と乖離するため。
chmod 777 "$DEST" "$DEST/var" "$DEST/public_html"

if [ ! -f "$DEST/public_html/admin/index.html" ]; then
  echo "⚠ public_html/admin/index.html が無い。先に 'npm run build --prefix frontend' を実行してください。" >&2
fi

echo "✓ 完了: $DEST"
echo "  次: docker compose -p nene-installer -f compose.installer.yaml up -d --build"
