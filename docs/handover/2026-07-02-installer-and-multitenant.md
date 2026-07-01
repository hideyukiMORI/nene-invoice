# 引継ぎ 2026-07-02 — インストーラ & マルチテナント（型B Phase 1）

> これを読めば状況が分かり、続きができる状態にするためのファイル。
> 併読: `_work/discussion-log/2026-07-02.md`（戦略・意思決定）、`docs/todo/current.md`、各 ADR。

## TL;DR（今どこ）

- **インストーラ（Tier A / install.php）はシングルテナントで実機健全**（設置→ログイン→CRUD→**PDF** まで捨てコンテナで確認）。任意ディレクトリ設置も可（ADR 0015 実装済）。
- **マルチテナントは方針＝型B（superadmin=ホストが払い出し／顧問先は自 org にログインして発行）で確定し、Phase 1 を実装・実機検証・マージ済み**。
- 次は **Phase 2**（顧問先の SPA を `/{slug}/` 配下で使える FE 導線ほか）。#552 は Phase 2 用に open 継続。

## 今日マージした PR（すべて main・CI 全緑 e2e 含む）

| PR | Issue | 内容 |
| --- | --- | --- |
| #540 | #505 | 銀行入金消込 増分⑤（確認→起票・候補生成・消込SQL）※本日の別作業で既済 |
| #541 | #505 | 同 増分⑦（HTTP＋OpenAPI） |
| #542 | #505 | 同 増分⑧（消込ワークベンチUI）→ **#505 close**（⑥手数料 write-off は税理士ゲートで #543 に分離） |
| #539 | #538 | 月境界の日付フレーキーテスト修正（CI 緑化の前提） |
| #545 | #544 | installer: テナント選択(single/multi)＋手動アップロード取得(SHA-256) |
| #547 | #546 | installer: マルチ選択を「上級者向け・API運用前提」と明記 |
| #549 | #548 | **ADR 0020**（遅延フォントパック）記録 |
| #551 | #550 | **[P1] fix: リリースZIPに resources/（IPAexフォント）同梱**（設置後 PDF 500 を修正）＋回帰テスト |
| #553 | #552 | multitenant: `/admin/me` を org リゾルバ bypass（superadmin org-less ブート） |
| #554 | #552 | **multitenant 型B Phase 1**（superadmin プロビジョニング＋組織管理UI） |

## インストーラの状態

- 3ステップ（要件→DB＋利用形態→管理者）。**single**＝組織＋admin、**multi**＝superadmin(org=NULL)。手動アップロード＝ZIP を SHA-256 照合→zip-slip 対策付き `ROOT` 展開。任意ディレクトリ設置は `BasePath`/`SpaShell`/base 相対 cookie で対応（ADR 0015 実装・テスト済）。
- **未解決の設計課題＝ペイロード 84MB（IPAex 同梱後）**。共有ホスティングの upload 上限で手動アップロードが詰まり得る。→ **ADR 0020「遅延フォントパック」で対応方針決定・実装は #548（未着手）**。要点: 巨大フォントを本体から外し、サーバ側でオンデマンド取得・検証・キャッシュ、取得前は fail-closed。配信元は Origin 稼働待ち（暫定 GitHub Releases 可）。
  - 74MB の内訳＝ほぼ mPDF フォント。**本文＝mPDF既定 `Sun-ExtA`(22MB)＋`Sun-ExtB`(17MB)**、見出し＝同梱 IPAex（`PdfStyle` の body は font 未指定＝既定依存）。見た目維持の静的剪定は ~50MB 止まり（弱い）。本文 IPAex 化（~20MB・より正しい日本語組版だが見た目変更）は ADR 0020 Option 3 として温存。

## マルチテナント 型B の状態

- **決定（討議 2026-07-02）**: 型B＝provisioning superadmin＋顧問先ごとログイン。用途は税理士/MSP の「おまかせ運用 SKU」（`next-move-report.md` P1-5）。型A（横断スイッチャー）は後段の上物として live、型C（Suite 寄せ）は単独では弱い。
- **Phase 1（実装・実機検証・マージ済 = #554）**:
  - BE: `POST /admin/organizations` を拡張し **org＋初期admin を原子的に払い出し**（`InitialAdminRepository`・org id は新規org固定・role/status 固定・email 重複 409・org-scoped `CreateUserUseCase` 隔離は無改変）。superadmin 専用。OpenAPI＋用語登録＋テスト（原子性は実SQLite tx で証明）。
  - FE: `entities/organization`＋組織管理（一覧/作成〔初期admin トグル〕/削除）。**role=superadmin(org 無し) は `/organizations` に着地し org スコープ請求ナビを隠す**（`app/home-redirect.tsx`・`AppShell`）。
  - 実機（installer-demo・multi/path）: superadmin `/admin/me`(org null) OK ／ 払い出し 201 ／ 片方だけ 422 ／ 重複 409 ／ 顧問先 admin ログイン(role admin/org 1) OK。
- **Phase 2 の朗報**: **path テナンシーは API レベルで既に end-to-end 稼働**（`/acme/admin/dashboard`→200、`/admin/dashboard`(prefix無)→404 は想定）。

## 次にやること（Phase 2・優先順は要相談）

1. **顧問先の SPA を `/{slug}/` 配下で配信・ルーティングする FE 導線**（＝顧問先が普通にブラウザで使える。ADR 0015 base-path × org prefix の合流）。★本命：これが「顧問先が実際に使える」に直結。
2. superadmin の **per-route guard**（org スコープ URL 手打ちの保護。現状は着地/ナビのみ role ゲート）。
3. 「おまかせ運用パック」（P1-5）: 一括 org 作成・データ分離/課金分界ガイド・PWA 発行導線・サービストークン委任・**組織の停止/更新 API**（現状 list/create/get/delete のみ）。#527 一括発行とセットで会計事務所¥49,800 を解錠。
4. インストーラ #548（遅延フォントパック）実装（配信元は Origin 稼働 or 暫定 GitHub Releases）。

## 重要な発見・落とし穴（明日ハマらないため）

- **リリースZIPは runtime 参照ディレクトリを全部含める**。`resources/`（IPAexフォント）漏れで設置後 PDF が全滅した（#550）。`tests/Installer/ReleaseContentsTest` が「src が `__DIR__.'/../../<dir>'` で参照する repo 直下は build-release が必ず同梱」を検証（再発防止）。
- **日付依存テストは FixedClock 由来に**（実クロック禁止）。月境界で main が赤になった（#538）。`fixedclock-test-time-dependence` メモリ参照。
- **path マルチテナントの API アクセスは `/{slug}/admin/...`**（prefix 無しは org 未解決で 404 が正）。`/admin/me` は bypass 済で org 無しでも通る。
- SPA base-path は `SCRIPT_NAME` から検出（install 位置）。org slug とは別軸 → 顧問先 SPA を org prefix 下で出すのが Phase 2 の要。

## 再開手順（環境）

- dev スタック: `docker compose up -d --build` → http://localhost:8510（admin@example.com / password123）。
- 品質ゲート: backend `composer check`（test/analyse/cs/openapi/mcp）、frontend（`frontend/`）`npm run check`。
- 捨てコンテナ実機テスト: `sh docker/installer-demo/setup.sh` → `docker compose -p nene-installer -f compose.installer.yaml up -d --build`（app:8595 / 空 MySQL）。install.php を叩いて single/multi を検証。**後片付け必須**: `down -v` ＋ `rm -rf /tmp/nene-installer-demo`。
- リリースZIP: `bash tools/build-release.sh dev`（dist/、gitignore 済）。
- ワークフロー厳守: Issue 先行・main 保護（PR 経由）・コミット形式 `<type>(<scope>): <日本語> (#issue)`。

## ポインタ

- Open Issue: **#552（multitenant Phase 2）**、#548（遅延フォントパック）、#543（銀行消込 手数料 write-off・税理士ゲート）、#527（一括発行）。
- ADR: 0006（マルチテナント/ロール）、0015（base-path）、0018（署名付きOrigin/自己更新）、**0020（遅延フォントパック）**。
- 討議ログ: `_work/discussion-log/2026-07-02.md`（型B 決定＋Phase 1 完了追記／Records installer の横断議論も別セクションに有り）。
- アシスタント人格＝**リナ**（施主指定・メモリ `persona-rina`）。
