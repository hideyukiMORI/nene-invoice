# 引き継ぎ書 — 指示書 design 04 デザイン反映（2026-06-05）

NeNe Invoice フロントエンドの「指示書 design 04（`/tmp/d04/NeNe-Invoice-一式/`）への
デザイン忠実化」作業の引き継ぎ。ユーザー（プロダクトオーナー）が画面を1つずつ指示書と
見比べ、差異を順次指摘 → 1指摘 = 1 Issue/PR で対応している。

---

## 1. 完了済み（マージ済み PR）

| PR | Issue | 内容 |
| --- | --- | --- |
| #287 | #286 | ダッシュボード請求発行額を `.iss-grid3` 3カラム白カードに刷新 |
| #288 | #286 | サイドバーフッターを `.side-foot`/`.sf-*` 構成に刷新 |
| #289 | #286 | カレンダー角丸復元（dp-pop13/day10/nav9/foot9）＋設定フォーム白カード3分割 |
| #291 | #290 | 一覧の削除アクションを枠線なし赤文字リンクに（ListUsers/ListClients） |
| #293 | #292 | 作成ボタンの `n` キーキャップ廃止＋「{対象}を作成」ラベル統一 |
| #295 | #294 | 見積・請求の状態バッジを `.badge-status`(min-width:68px) で揃える |
| #297 | #296 | サイドバーのカテゴリ見出し(概要/取引/管理)表示＋メニュー文字を明るく(medium) |
| #299 | #298 | body に `palt` カーニング適用＋`.page-sub` を12pxに（2026-06-05 マージ済み） |

## 2. レビュー中（CIグリーン → マージ待ち）

| PR | Issue | 内容 | 状態 |
| --- | --- | --- | --- |
| #301 | #300 | 本文ベースフォントを12.5pxに（`--text-body` 0.8125→0.78125rem、指示書案C準拠） | **CIグリーン**。owner 目視レビュー後マージ |
| #303 | #302 | 検索/フィルタUIを指示書 `.filter-bar` 構成に統一（共有 `FilterBar` 新設、4画面載せ替え） | **CIグリーン**。owner 目視レビュー後マージ |

> #300 補足: 「root フォントが大きい」の実体は本文 13px vs 指示書案C(`.dir-c`)
> `--fs-base: 12.5px` の 0.5px 差。前版引き継ぎ書は案C上書き**前**の既定値(13px)を見て
> 「一致・変更不要」と誤判定していた。html ルートは 16px のまま（rem トークン総崩れ回避）、
> `--text-body` トークンのみ較正。raw `0.8125rem`（`.panel-head h3` 等）は別コンポーネント
> 個別サイズのためスコープ外（必要なら今後1つずつ）。

> #302 実装メモ: `shared/ui/components/FilterBar.tsx`（`.filter-bar/.filter-grid/.filter-foot/
> .filter-count`）。`footStart` スロットで請求の「期限超過のみ」を foot に配置。各 view に
> `total` を公開し「表示中 N 件」表示。共有 i18n `admin.filter.{apply,reset,shownLabel,shownUnit}`
> に集約（画面個別の `*.filter.apply/reset` は撤去）。640px 以下で foot 折返し `@media` 追加。

## 3. 未着手（残タスク）

（3-A 検索エリア統一は #302/#303 で完了。次の owner 指摘待ち。）

---

## 4. 重要な発見：型チェックが実質ノーチェック（要相談）

**フロントエンドの `npm run type-check`（＝`tsc --noEmit`）が何も検査していない。**

- 原因: root `frontend/tsconfig.json` が `"files": []` ＋ `references` のみ。
  `tsc --noEmit` は参照を辿らず（`-b` 無し）、対象ファイル0件で**素通り**する。
  `npm run build`（`tsc --noEmit && vite build`）の tsc 部分も同様に無意味。
- 影響: 型エラーが CI/ローカルで検出されない。実際 `tsc -p tsconfig.app.json` を
  単体実行すると**既存エラーが10件以上**出る。例:
  - `AppShell.tsx` の `admin.nav.group.*` キー欠落（→ #296 で修正済み。これが
    「カテゴリ見出しが空表示」の真因だった）
  - `exactOptionalPropertyTypes`/`noUncheckedIndexedAccess` 由来の各種（test群、
    `AccountMenu.tsx` の `email[0]`、`ViewDashboard.tsx` の StatCard foot など）
  - `use-view-quote.test.ts` の `changeStatus`/`ViewQuoteState`（**別エージェントの
    作業中の可能性** — 下記参照）
- 提案: 別 Issue で「type-check を `tsc -b` もしくは `tsc -p tsconfig.app.json` に
  修正」＋露出する既存エラーの一掃。ただし**並行作業エージェントの未完成コードを
  巻き込む**恐れがあるため、着手タイミングはユーザー確認の上で。

---

## 5. root フォントサイズ（調査済み・変更不要と判断）

ユーザー指摘「root フォントが大きい？指示書サイズに」について：

- 指示書: `html { font-size: var(--fs-base) }`、`--fs-base: 13px`。中身はほぼ全て px 指定。
- フロント: html は 16px（ブラウザ既定）。ただし `--text-*` トークンが rem 分数で
  較正済みで、**算出 px は指示書と一致**：
  - body `0.8125rem`×16 = 13px（指示書 13px）
  - page-title `1.3125rem`×16 = 21px（指示書 21px）
- 結論: root を 13px に変えると全 rem が 0.81倍に縮みトークンが総崩れ。フォントサイズは
  既に一致しているため**変更しない**。見え方の差は palt カーニング欠落が主因で、#299 で対応。

---

## 6. 開発・運用メモ

- ポート: Vite `5185` / PHP `8510`（CLAUDE.md のローカルポート規約）。dev ログインは
  `admin@example.com` / `password123`。
- 各 PR の確認手順: `npm run lint && npx prettier --check <files> && npm run knip &&
  npm run test && npm run build` ＋ Playwright で実画面スクショ確認
  （`e2e/_tmp/*.spec.ts` を一時作成して撮影 → 削除、のパターンを使用）。
  ※ `npm run type-check` は前述の理由で当てにならない。必要なら
  `npx tsc -p tsconfig.app.json` で実チェックし、**自分の変更が新規エラーを足して
  いないか**だけ確認する運用にしている。
- ブランチ運用: `main` から `design/<issue>-<summary>`。直 push 禁止。コミット末尾に
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`。
- **並行作業エージェント注意**（メモリ `parallel-agent-collision-risk`）: 本リポジトリは
  Cursor エージェントが同時編集することがある。コミット前に `git status` / ブランチを
  必ず確認。`tsc -p tsconfig.app.json` で見えた `changeStatus`/`ViewQuoteState` 等の
  エラーは当方の変更ではない（恐らく並行作業の途中状態）。
- 案C「高密度オペ・角ゼロ」採用済み（`--radius-sm: 0`）。ただし datepicker は指示書で
  明示 px 角丸のため角丸維持（#289）。バッジ等は角ゼロ。

---

## 7. 再開時の最初のアクション

1. `gh pr checks 299` → グリーンなら `gh pr merge 299 --merge --delete-branch` →
   `git checkout main && git pull`。
2. 残タスク **3-A（検索エリア統一）** に着手。Issue を切り、まず指示書 `id="audit"` の
   `.filter-*` マークアップ/CSS を精読 → `ListAuditLogs.tsx` を基準に組み、他 List* へ展開。
3. 型チェック修正（セクション4）はユーザーに方針確認してから。
