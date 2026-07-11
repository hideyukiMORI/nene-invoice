# dist/ — リリース ZIP の出力先（ビルド成果物・untracked）

ここに置かれる `nene-invoice-<version>.zip`（＋ `.sha256` サイドカー）は
`tools/build-release.sh` の**その時点のローカルビルド成果物**であり、リポジトリには
含まれない（`.gitignore`）。**このフォルダの zip を「最新」と信用しないこと。**

## 配布前の鉄則（#630 の再発防止）

2026-07-11 の構造統一性監査で、`Nene2\Demo` consumer 化（#610/#616）**前**の旧世代
zip がファイル名だけ最新に見える状態で残置され、誤配布リスクになっていた（#630）。

1. **配布する zip は、必ず配布直前に現 `main` から焼き直す**:

   ```bash
   git checkout main && git pull
   bash tools/build-release.sh <version>
   ```

2. ビルドログの `nene2 resolved: vX.Y.Z` が `composer.json` の
   `require."hideyukimori/nene2"` と整合していることを確認する
   （制約は composer.json から自動で読まれる — #629）。

3. 古い zip は残さない。用済みの zip・サイドカーは削除する
   （`rm -f dist/nene-invoice-*.zip*`）。zip の鮮度はファイル日付でしか判定できず、
   ファイル名のバージョンは中身の世代を保証しない。

## 検証

展開物の設置検証は `compose.installer.yaml`（インストーラ検証用 Docker 環境）を使う。
