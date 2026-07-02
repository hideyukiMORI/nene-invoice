# 引継ぎ 2026-07-03 — 型B Phase 2 完了 ＋ 掃除 ＋ S2 連携

> これを読めば状況が分かり続きに入れる。併読: `docs/handover/2026-07-02-installer-and-multitenant.md`（前日・型B Phase 1／インストーラ）、`_work/handoff-2026-07-02.md`（インストーラ toolkit／S2 の正本・別セッション Opus リナ が保守）、`_work/discussion-log/2026-07-02.md`（設計・意思決定 SoT）。アシスタント人格＝リナ。

## TL;DR（今どこ）

- **型B マルチテナント Phase 2 完了**（3スライス main マージ）。「superadmin がホストとして org を払い出す → 顧問先が `/{slug}/` で自 org にログインして発行 → 役割外 URL は安全に redirect → superadmin が org を停止/更新」まで **API/UI が一通り閉じた**。
- **浮遊ファイル掃除完了**（#564）。リポ内 untracked の意図的資産を tracked 化、リポ未所属 zip は `_work/assets/` へ。
- **インストーラ共通 toolkit（S1〜S3）は別レーン**（NENE2＝Opus リナ実装／関所＝Fable リナ）。invoice の S2（consumer 化）は **NENE2 1.6.0 リリース待ちで未着手**。取り込み方針＝ハイブリッド（施主決定・下記）。

## 今日 main にマージした PR（すべて CI 緑）

| PR | Issue | 内容 |
| --- | --- | --- |
| #557 | #556 | **型B Phase 2 ①** 顧問先 SPA を `/{slug}/` 配下で end-to-end 稼働（path slug-strip ルーティング＋SPA app-base 分離注入） |
| #559 | #558 | **型B Phase 2 ②** superadmin/顧問先の per-route guard（FE・`app/require-role.tsx`） |
| #561 | #560 | **型B Phase 2 ③** 組織の停止/更新 API（`PATCH /admin/organizations/{id}`・is_active/name/plan） |
| #564 | #563 | docs: 浮遊していた意図的ドキュメント資産を tracked 化（persona-review 等 34 files） |

main tip（session 終了時）= **`ffc0b29`（Merge #564）**。`hideyukimori/nene2: "^1.5"`（Packagist・クリーン）。

## 型B Phase 2 の詳細

### ① path テナンシー SPA（#557）— 着手時に前提の誤りを発見
- **引継ぎの「path テナンシーは API レベルで既に end-to-end 稼働（`/acme/admin/dashboard`→200）」は誤りだった**。`PathPrefixResolutionStrategy` は slug を抽出するが**誰も path から slug を除去していなかった**。NENE2 Router は full path を anchored regex 照合するため `/{slug}/admin/*` は `/admin/*` に一致せず 404。
- 実装:
  - **slug-strip ルーティング**: `PathScopedResolutionStrategyInterface`（新）を `PathPrefixResolutionStrategy` が実装。`OrgResolverMiddleware` が org 解決後に `/{slug}` を URL から剥がす。**最前段で剥がすのがセキュリティ必須**＝`/admin`・`/api` prefix で判定する bearer-token/capability ガードと Router が canonical パスを見る（後段で剥がすと prefix 認証をすり抜ける）。
  - **SPA app-base**: `SpaShell::serve($assetBase, $appBase)` に分離。純粋・testable な `SpaBasePlan` が install base（ADR 0015）× org slug を合流し、`/{slug}/…` のシェルに `<meta app-base="<install>/<slug>/">` を注入（router basename＋apiUrl が slug を向く）。アセットの `<base href>` は install 相対のまま（実体1コピー）。slug 判定は `findBySlug`（path モードのみ DB 参照）。
  - FE `app-base.ts` は multi-segment 対応済でロジック無改変・テスト追加のみ。
- **認証はグローバル**: `uniq_users_email`（email がグローバル一意）ゆえ org はユーザーレコードから決まる。`/{slug}/auth/*` は解決＋除去で無害に `/auth/*` へ。
- 実 HTTP+MySQL（path・捨てサーバ）で確認: `/default/`→200＋`app-base=/default/`、`/default/admin/dashboard` 無token→401/token→200（org スコープ）、`/admin/dashboard`(prefix無)→404、`/organizations`(org-less)→`app-base=/`。単一モード回帰なし。

### ② per-route guard（#559）— 穴は FE のみ
- **BE は既に堅い**: `OrgGuardMiddleware` が token-org≠resolved-org を 403（superadmin のみ cross-tenant 免除）。admin/member/viewer は capability(`ManageOrganizations`) 無しで `/admin/organizations` を 403。→ **BE のクロステナント防御に穴なし・変更不要**。
- 穴は FE の**ナビ出し分け（リンクを隠すだけ）**。URL 直叩きで org-less superadmin が org 画面→404、顧問先 admin が `/organizations`→BE 403 の壊れたページ。
- `app/require-role.tsx`＝pathless layout guard: `audience=org` は org-less superadmin を `/organizations` へ、`audience=superadmin` は非 superadmin を `/dashboard` へ redirect。`router.tsx` で org-scoped 群／organizations 群を各 guard 配下へ（分類は AppShell `NAV`/`SUPERADMIN_NAV` と一致）。RBAC は UX のみ・enforcement は BE。

### ③ 組織の停止/更新 API（#561）
- `PATCH /admin/organizations/{id}`（superadmin 専用・`ManageOrganizations` で自動ゲート）。partial: `is_active`＝停止(false)/再開(true)、`name`/`plan`＝更新。`array_key_exists` で「`is_active:false`」と「キー欠落」を区別。
- `slug`（URL 同一性）・`external_id`（federation・ADR 0016）は immutable（既存値保持）。監査 `organization.updated`（before/after）を write と同一 tx。既存 `update()`＋404 を再利用（Delete フローの写し）。
- 停止すると `OrgResolverMiddleware` が当該 org を 403 でロックアウト（データは残す）。実 HTTP 非破壊確認: 無token→401／admin(非superadmin)→403。live の 200 更新は superadmin が要るため UseCase/Handler テスト＋同型 Delete でカバー。

## インストーラ共通 toolkit（S1〜S3）＝別レーン・invoice S2 の状態

- **正本は `_work/handoff-2026-07-02.md`**（Opus リナ＝実装／Fable リナ＝仕様の関所）。**NENE2 は触らない**（同一リポ2セッション禁止）。
- toolkit 進捗（NENE2 main・実 git 裏取り済）: **S1(payload/preflight) ✅ / B1 TenantConfigurationValidator ✅ / B2 DatabaseSchemaApplier ✅ / C-1a ReleaseManifestParser ✅ / C-1b ReleaseSource+HttpTransport ✅ merged**。残 **C-2 InstallerTemplateInterface＋既定ウィザードUI**。
- **invoice の S2（`public_html/install.php` 1,320行→toolkit consumer 化）は release 待ちで未着手**。invoice は NENE2 を Packagist `^1.5` 純依存で、`v1.5.333` に `src/Install/` は 0 ファイル＝toolkit は未 release。
- **【施主決定 2026-07-02・→Opus リナ】S2 取り込み＝ハイブリッド（release は GO ゲート）**（`_work/handoff` S2 節＋討議ログ 議題2 #5 に固定済）:
  1. S2 ブランチは **path repo で local NENE2 開発**（`composer.json` path 化は S2 ブランチ内限定・マージ前に packagist `^1.x` へ必ず戻す／toolkit 修正は NENE2 へ PR・Fable review）。
  2. S2 機能完成＋path 実機スモーク通過で **NENE2 1.6.0 release**（minor＝後方互換機能追加）tag→Packagist。
  3. invoice を `^1.6`＋`composer update` に切替 → **--no-dev/migrate 回帰/ZIP 実測/捨てコンテナ実機（設置→ログイン→PDF）を packagist 版 vendor で最終実行 → S2 マージ**。
  4. **release（外向き）は②の tag/公開前に hide の「GO」必須**。
- **S2 の schema 決定A（施主）**: fresh install も phinx（`DatabaseSchemaApplier`）へ統一。`robmorgan/phinx` を **require-dev→require** 移動・`--no-dev で migrate 動作`を回帰必須・`database/schema/*.sql` は参照資料へ降格。→ これが乗ると memory `installer-schema-parity`（手書き schema.sql 払い出し）は書き換え。

## ⚠️ 並行セッション衝突の申し送り（重要）

- **invoice 作業ディレクトリ `/home/xi/docker/nene-invoice` を、あたし（invoice）と Opus リナ（S2）が共有**している。片方の `git checkout` が相手の作業ツリーを書き換える。
- 今日実際に起きたこと: (a) S2 の path-repo commit が一時 **ローカル main** に乗り、そこから切った docs ブランチが composer.json/lock を継承→#564 の CI が `../NENE2 not found` で fail。→ **#564 を origin/main へ rebase して是正**。(b) `gh pr merge #564 --delete-branch` 後、**共有ツリーが S2 ブランチ `refactor/562-install-toolkit-consumer` に自動切替**された（作業ツリーはクリーン＝S2 の WIP は壊していない・あたしは一切 touch せず）。
- **現状（session 終了時）**: origin/main クリーン（`^1.5`）／ローカル main クリーン（`0a117f9`・`^1.5`）／path-repo `../NENE2` は **S2 ブランチ `refactor/562` にのみ存在**＝施主プラン①「S2 ブランチ内限定」どおりに収束。**追加対応不要**。
- **教訓（memory `parallel-agent-collision-risk` に反映）**: 共有リポで invoice 作業をするときは (1) `origin/main` から切る（ローカル main を信用しない）、(2) `gh pr merge --delete-branch` の後処理でブランチが勝手に切り替わるのを警戒、(3) commit を伴う作業は **リンク worktree（`git worktree add <scratchpad> origin/main`）で隔離**する（この handover 自体もそれで作成）。

## 次にやること

1. **#453**（`fix/452-datepicker-popover-overflow`・open）を **origin/main へ rebase → CI の fail を見て判断**（直せば小さく閉じる・腐ってればクローズ）。※共有ツリー衝突回避のため worktree で。
2. **#527 一括発行・一括メール**＝**製品側の本命**。型B Phase 2 がほぼ閉じた今、おまかせ運用 SKU（会計事務所 ¥49,800）の残りの鍵。S2 の次はこれ。
3. 型B Phase 2 の残り（任意）: superadmin 組織管理 UI に**停止/更新の導線**（#561 API の FE 消費）。
4. S2 本体は NENE2 1.6.0 release 後（上記ハイブリッド手順）。C-2 完成待ち。
5. `#426` Qiita は寝かせたまま（宣材完了後の (C)）。

## 反映漏れ・注意

- **`docs/todo/current.md`（07-01 付）は今日の型B Phase 2 マージ3本＋#562 起票を未反映**（施主判断で「S2 初スライスが乗るタイミングでまとめて」＝Opus リナのセッション末更新に委譲・🟢想定内）。
- board の「#526 定期請求」は 06-30 クローズ済＝board から是正落とし済。harvest は issue の open/close 突合を手順に入れること。
- `docs/articles/qiita-...`（untracked）は **open PR #426 の所有物**＝触らない。
- リポ未所属 zip は `_work/assets/nene-invoice-installer-mockup.zip` へ退避済。

## 再開手順（環境）

- dev スタック: `docker compose up -d --build` → http://localhost:8510（admin@example.com / password123）。Vite: `npm run dev --prefix frontend`（5185）。
- 品質ゲート: backend `composer check`（test/analyse/cs/openapi/mcp）、frontend（`frontend/`）`npm run check`。
- 捨てコンテナ実機（installer-demo）: `sh docker/installer-demo/setup.sh` → `docker compose -p nene-installer -f compose.installer.yaml up -d --build`。後片付け必須（`down -v`＋`rm -rf /tmp/nene-installer-demo`）。
- ワークフロー厳守: Issue 先行・main 保護（PR 経由）・コミット形式 `<type>(<scope>): <日本語> (#issue)`。
- **共有作業ディレクトリ注意**: S2 セッションと同居。commit 作業は worktree 隔離＋`origin/main` 基点で。

## Open Issue / ポインタ

- Open: **#562**（S2 install toolkit consumer・別ブランチ `refactor/562`）、#453（datepicker・rebase 判断待ち）、#426（Qiita・寝かせ）、#552（型B umbrella・Phase 2 は実質完了）、#548（遅延フォントパック）、#543（銀行消込 手数料 write-off・税理士ゲート）、#527（一括発行）。
- ADR: 0006（マルチテナント/ロール）、0015（base-path）、0016（federation）、0020（遅延フォントパック）。
- memory: `multitenant-typeB-status`（Phase 2 完了反映済）、`parallel-agent-collision-risk`（今日の教訓反映）、`installer-schema-parity`（S2 決定A で将来書換）、`installer-acquisition-model`、`base-path-install-adr0015`。
