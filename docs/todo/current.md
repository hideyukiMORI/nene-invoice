# Current Work

Last updated: 2026-06-04 (Issue #253; merged through #252)

## Recently merged

- **Issue #1 / PR #2** — Governance and foundation (workflow, naming, ADRs, review checklists, Cursor rules)
- **Issue #3 / PR #8** — Product vision, requirements, domain model ✅ merged
- **Issue #9 / PR #10** — 適格請求書ドキュメント精度修正（端数処理 ADR 0004・登録番号・用語・命名）✅ merged
- **Issue #11 / PR #12** — 日英(ja/en)バイリンガル方針宣言（ADR 0005）・多言語非対象 ✅ merged
- **Issue #13 / PR #14** — 会計・税務コンプライアンス完全順守を拘束ルール化（accounting-compliance.md・compliance チェックリスト）✅ merged
- **Issue #15 / PR #16** — 命名規則の絶対順守・用語レジストリ（唯一の真実）・タイポ厳禁 ✅ merged
- **Issue #17 / PR #18** — マルチテナント基盤＋ロール階層を土台採用（ADR 0006） ✅ merged
- **Issue #4 / PR #19** — ランタイム基盤: NENE2 consumer scaffold + `GET /health` ✅ merged
- **Issue #20 / PR #21** — Organization 永続化レイヤ（テナント）+ Phinx マイグレーション基盤 ✅ merged
- **Issue #22 / PR #23** — ランタイムに DB 接続（AppConfig）+ DatabaseHealthCheck を配線 ✅ merged
- **Issue #24 / PR #25** — User 永続化 + Role/Capability(RBAC) ドメイン ✅ merged
- **Issue #26 / PR #29** — JWT ログイン（POST /auth/login）+ トークン発行 ✅ merged
- **Issue #30** — Bearer トークンゲート（/admin/ 保護）+ GET /admin/me ✅ merged（commit `f412a52`）
- **Issue #33 / PR #34** — 並行 Cursor 由来 #27/#31 の revert（NeNe Clear 等を除去）✅ merged
- **Issue #35 / PR #36** — CapabilityMiddleware + CapabilityResolver（RBAC 強制）✅ merged
- **Issue #37 / PR #38** — 組織 CRUD（superadmin・/admin/organizations）✅ merged
- **Issue #39 / PR #40** — ユーザー読み取り（/admin/users）+ org スコープ ✅ merged
- **Issue #41 / PR #42** — ユーザー write（create/update/delete）✅ merged
- **Issue #43 / PR #44** — Client（取引先）永続化 + 読み取り ✅ merged
- **Issue #45 / PR #46** — Client write（create/update/delete）✅ merged
- **Issue #47 / PR #48** — 発行者プロフィール（company settings）+ 登録番号検証共有化 ✅ merged
- **Issue #49 / PR #50** — 税計算エンジン（ADR 0004）✅ merged
- **Issue #51 / PR #52** — 監査ログ基盤（ADR 0008）+ Client 統合 ✅ merged
- **Issue #53 / PR #54** — 監査を Organization / User / CompanySettings に展開 ✅ merged
- **Issue #55 / PR #56** — 文書採番（document_sequences）✅ merged
- **Issue #57 / PR #58** — line_items 永続化（quote/invoice 共有）✅ merged
- **Issue #59 / PR #60** — Quote 永続化レイヤ ✅ merged
- **Issue #61 / PR #62** — Quote 作成/一覧/取得（結線）✅ merged
- **Issue #63 / PR #64** — Quote 状態遷移 ✅ merged
- **Issue #65 / PR #66** — Invoice 永続化レイヤ ✅ merged
- **Issue #67 / PR #68** — 見積→請求書変換 + 請求書一覧/取得 ✅ merged
- **Issue #69 / PR #71** — 請求書の発行（INV 採番 + 適格請求書検証）✅ merged
- **Issue #72 / PR #73** — 入金記録（payments）— paid/partially_paid 遷移 ✅ merged
- **Issue #74 / PR #75** — 請求書の直接作成（POST /admin/invoices）✅ merged
- **Issue #76 / PR #77** — 監査ログ参照 API（GET /admin/audit-logs）✅ merged
- **Issue #5 / PR #78** — OpenAPI stub + MCP カタログ ✅ merged
- **Issue #6 / PR #79** — Backend CI workflow（GitHub Actions）✅ merged
- **Issue #80 / PR #81** — CI Actions を Node 24 対応版（checkout/cache @v5）へ更新 ✅ merged
- **Issue #7 / PR #82** — ADR 0003 dual deployment（Tier A / Tier B）✅ merged
- **Issue #83 / PR #84** — フロントエンド規約を本実装版へ拡張（sibling 準拠）✅ merged
- **Issue #85 / PR #86** — admin UI スキャフォールド + ログイン〜請求書一覧 ✅ merged
- **Issue #87 / PR #88** — Vite dev の base 修正（import 解決）✅ merged
- **Issue #89 / PR #90** — 請求書詳細画面（/invoices/:id・明細表示）✅ merged
- **Issue #91 / PR #92** — 請求書作成フォーム（client 選択 + 明細）✅ merged
- **Issue #93 / PR #94** — 請求書の発行アクション（下書き詳細から issue）✅ merged
- **Issue #95 / PR #96** — 入金記録（発行済み詳細で入金フォーム＋入金一覧）✅ merged
- **Issue #97 / PR #98** — NeNe Clear 上流契約の受諾＋ガバナンス整備（ADR 0009 / sibling / 用語）✅ merged
- **Issue #99 / PR #100** — 売掛金残高 `outstanding_cents` を read モデルに公開 ✅ merged
- **Issue #101 / PR #102** — `/api/*` サービス面 + サービストークン認証 + 請求書 read（Clear 向け）✅ merged
- **Issue #103 / PR #104** — フロント: 請求書の残高 `outstanding_cents` を一覧・詳細に表示 ✅ merged
- **Issue #105 / PR #106** — `GET /api/invoices` に read フィルタ（status/overdue/client/due/outstanding）✅ merged
- **Issue #107 / PR #108** — フロント: 取引先一覧画面 + ヘッダーナビ ✅ merged
- **Issue #109 / PR #110** — payment external_reference / idempotency_key / void データ層 ✅ merged
- **Issue #111 / PR #112** — `POST /api/invoices/{id}/payments`（冪等・external_reference・過入金422）✅ merged
- **Issue #113 / PR #114** — `POST /api/invoices/{id}/payments/{paymentId}/void`（void-with-audit・冪等）✅ merged
- **Issue #115 / PR #116** — フロント: 取引先の作成フォーム（/clients/new）✅ merged
- **Issue #117 / PR #118** — フロント: ConfirmDialog primitive + 取引先の削除 ✅ merged
- **Issue #119 / PR #120** — フロント: 取引先の編集（/clients/:id/edit）✅ merged
- **Issue #121** — フロント: 請求書の発行を確認ダイアログ化 ⏳ this PR

## Active

現在オープンな作業ブランチなし（Phase 1–3・セキュリティ診断・UX 強化はすべて merged）。新規作業は Issue を立ててから着手する。直近の完了は「直近の追加実装（Issue #201 以降）」を参照。

> 以下の「NeNe Clear 連携」「Frontend 画面の進め方」「Phase 0+ Backlog」は実装当時の計画メモ（履歴）。記載のフォローアップ（ConfirmDialog 化・due_at 入力・取引先 CRUD・一覧ページング・Tier A 配信・PDF・openapi.php 配信 等）はいずれも merged 済み。残課題は上記「技術的負債」のみ。

### NeNe Clear 連携（入金消込・督促、downstream consumer）

契約原本: `nene-clear/docs/integrations/invoice-upstream-contract.md`。受諾 = **ADR 0009**。
- 方針: Invoice が請求・入金の SoR。Clear は HTTP の read + scoped write のみ。service スコープ **`/api/*`** 名前空間（独立 OpenAPI）+ **サービストークン principal**（`read:invoices` / `write:payments`、組織スコープ）。
- このPRはドキュメントのみ（ADR / sibling-products / terminology）。
- 後続（段階実装・別 Issue）: ①読み取りAPI（filters + `outstanding_cents` + payments 履歴）②書き込みAPI（idempotent payment create + `external_reference`、過入金→`payment-exceeds-outstanding`、void-with-audit）③サービストークン認証 ④`/api/*` OpenAPI + 契約テスト。
  - ①-a **済(#99)**: `outstanding_cents` を `/admin` の list/get read モデルに公開（PaymentRepo batch sum、純 read 派生・gate なし）。
  - ①-b **済(#101)**: `/api/*` サービス面 + サービストークン認証（`ServiceScope`/`ServiceAuthContext`/`ServiceScopeMiddleware`、BearerToken の保護 prefix に `/api/`）+ `GET /api/invoices` / `/api/invoices/{id}`（既存 UseCase 再利用、契約 read モデル + payments 履歴）。独立 OpenAPI `service-api.yaml`、`tools/issue-service-token.php`。ライブ確認: 401/403 分離・サービストークンで取得可。
  - ②-前半 **済(#105)**: `GET /api/invoices` の read フィルタ（status 複数 / client_id / due_before・due_after / overdue / outstanding_gt=0＝未回収）。すべて invoices 表のみで完結（payment JOIN なし、ページング整合）。任意閾値 outstanding_gt=N は follow-up。
  - ②後半 **税理士サインオフ済み（2026-05-30）→ gate 解除**。
    - W1 **済(#109)**: payments に `external_reference` / `idempotency_key`、`findById`/`findByIdempotencyKey`/`markVoided`（void=soft delete 流用）。
    - W2 **済(#111)**: `POST /api/invoices/{id}/payments`。RecordPaymentUseCase を拡張（冪等 replay / `external_reference` 保存 / 過入金→`PaymentExceedsOutstandingException`=422 `payment-exceeds-outstanding` + `outstanding_cents`）して operator/service 共用。`paid_at`=入金日。ライブ確認済み。
    - W3 **済(#113)**: `POST /api/invoices/{id}/payments/{paymentId}/void`。VoidPaymentUseCase（soft delete 流用の void-with-audit `payment.voided`、status 再計算 paid→partially_paid/issued、冪等）。`payment-not-found`(404) 追加。ライブ確認済み。
  - **②（読み取り＋書き込み）= 契約 §2/§3 完了**。残りは契約テスト（Clear 側）、運用フォロー: 複数 org スコープ、トークン発行/失効 UI。
- **コンプライアンス gate**: 書き込みAPI PR の前に、`paid_at`=入金日・外部起票・過入金は Clear 側 client_credit の各点を **税理士確認**（accounting-compliance.md は拘束）。

### Frontend 画面の進め方（縦スライス）

請求書 詳細(#89) ✅ → 作成(#91) ✅ → 発行(#93) ✅ → 入金(#95) ✅ ＝ quote-to-cash UI 一巡。残高表示(#103) で一覧/詳細に `outstanding_cents` を追加（codegen 再生成で取り込み）。
- entities: invoice（list/detail/create/issue）、client（list）、payment（list/record）、auth。
- 詳細ページが ViewInvoice + IssueInvoice + ManagePayments をページ合成（feature 間 import なし、useInvoice 共有）。payment mutation は invoice 無効化をフィーチャ側で実施（sibling entity 直接 import 回避）。
- 共有 UI: Button/Input/Select/Text/Stack/Spinner + Field/EmptyState/ErrorState（Storybook 必須）。
- 取引先一覧(#107): `features/list-clients` + `pages/clients`、AppShell に 請求書/取引先 ナビ。取引先 CRUD（作成/編集/削除）は後続。
- フォロー: 発行/入金の確認ダイアログ（ConfirmDialog primitive）、due_at 入力、取引先 CRUD 画面、一覧ページング、Tier A 同一オリジン配信の PHP 結線。

## Phase 0+ Backlog

| Issue | Topic | Priority |
| --- | --- | --- |
| — | Phase 3: Tier A web installer + release ZIP build + 運用ガイド（ADR 0003 フォロー） | P1 |
| — | `public_html/openapi.php`（spec の HTTP 配信）/ ランタイム応答↔example 突合 | P2 |
| — | overdue 表示 / 請求書 PDF・帳票 / 監査の transactional 記録 | P2 |

> マルチテナント前提のため、Issue #4 は単なる health から **テナント解決＋認証＋RBAC を含むランタイム基盤** に拡張。組織/ユーザー CRUD は後続 PR。

## State Summary

**Phase 0 — Governance: ✅ complete**

**Phase 0 — Product design: ✅ complete** (Issue #3 merged)

- Product vision, requirements, domain model documented
- Glossary expanded
- README updated

**Phase 0 — Product design polish: ✅ complete** (Issue #9)

- Tax rounding corrected to once-per-rate-per-document (ADR 0004)
- Registration number documented as syntax-only validation
- `cents` defined as smallest-currency-unit; naming inconsistency fixed

**Phase 0 — Localization scope: ✅ complete** (Issue #11)

- Product localization bound to ja (primary) + en (secondary); multilingual is a non-goal (ADR 0005)
- Statutory invoice content stays Japanese; en applies to operator UI/guides

**Phase 0 — Compliance hardening: ✅ complete** (Issue #13)

- Accounting/tax compliance made binding & non-negotiable (`accounting-compliance.md`)
- Compliance self-review checklist added (`docs/review/compliance.md`)
- Deviations require ADR + tax-professional sign-off

**Phase 0 — Terminology & naming hardening: ✅ complete** (Issue #15)

- Terminology registry added as single source of truth (`terminology.md`)
- Naming made absolute; typos / unregistered terms block merge
- Registry update required in the same PR as any term add/rename

**Phase 0 — Multi-tenancy adoption: ✅ complete** (Issue #17, docs/ADR only)

- ADR 0006: multi-tenant + role hierarchy (superadmin/admin/member/viewer) adopted as foundational
- Tenant-scoped `organization_id` on all billing tables; per-org issuer profile
- Org resolution default `single`; superadmin manages orgs, admin manages users
- terminology registry extended (roles, capabilities, resolution modes, org/user)

**Phase 0+ — Runtime scaffold (PR-B): ✅ complete** (Issue #4)

- NENE2 consumer bootstrap: `composer.json` (require `hideyukimori/nene2 ^1.5`), `public_html/index.php`, `RuntimeContainerFactory` → `RuntimeServiceProvider` → `ApplicationServiceProvider`
- `GET /health` (framework built-in) verified end-to-end; `composer check` green (PHPUnit + PHPStan 8 + php-cs-fixer)

**Phase 1 — Organization persistence: ✅ complete** (Issue #20)

- `organizations` table (Phinx migration + SQLite schema snapshot); `Organization` entity + `PdoOrganizationRepository`
- Phinx wired (`phinx.php`, `composer migrations:*`); `suffix => ''` so SQLite migrate/app share one file
- Repository tested on SQLite `:memory:`

**Phase 1 — Runtime DB connectivity: ✅ complete** (Issue #22)

- `RuntimeServiceProvider` wires ConfigLoader → AppConfig → PdoConnectionFactory → PdoDatabaseQueryExecutor
- `src/Http/DatabaseHealthCheck` (copied from NENE2 reference) registered on `GET /health`
- Verified: DB reachable → 200 `checks.database=ok`; unreachable → 503 `degraded`

**Phase 1 — User identity & RBAC domain: ✅ complete** (Issue #24)

- `Auth/Role` + `Auth/Capability` enums with capability matrix (superadmin/admin/member/viewer)
- `users` table (Phinx + SQLite snapshot); `User` entity + `PdoUserRepository` (org-scoped queries)
- Tested: role matrix + repository on SQLite `:memory:`

**Phase 1 — JWT login: ✅ complete** (Issue #26)

- `POST /auth/login` (public): verifies email+password, issues HMAC bearer token (`LocalBearerTokenVerifier`)
- Handler → `LoginUseCase` → `UserRepository`; token claims `sub`/`role`/`org`

**Phase 1 — Auth gate + current user: ✅ complete** (Issue #30, commit `f412a52`)

- `BearerTokenMiddleware` (prefix `/admin/`) wired into the runtime authMiddleware; `/health`, `/auth/login`, `/` stay public
- `GET /admin/me` returns the authenticated user from token claims (`sub`)
- Verified live: no/invalid token → 401, valid → 200 + user; `/health` public

**Cleanup — revert parallel-agent merges: ✅ complete** (Issue #33 / PR #34)

- Reverted Cursor-authored #27 (expansion-roadmap) and #31 (ADR 0007 NeNe Clear + philosophy)
- NeNe Clear is a *separate* sibling product (入金消込/督促), not this repo — do not reintroduce here

**Phase 1 — RBAC enforcement: ✅ complete** (Issue #35 / PR #36)

- `CapabilityResolver` (path+method → Capability) + `CapabilityMiddleware` (after BearerTokenMiddleware)
- authMiddleware = [BearerTokenMiddleware, CapabilityMiddleware]

**Phase 1 — Organization CRUD (superadmin): ✅ complete** (Issue #37 / PR #38)

- `GET/POST /admin/organizations`, `GET/DELETE /admin/organizations/{id}` — Handler → UseCase → repo
- Domain exceptions → Problem Details (404 / 409) via EXCEPTION_HANDLERS

**Phase 1 — User read + tenant isolation: ✅ complete** (Issue #39 / PR #40)

- `GET /admin/users`, `GET /admin/users/{id}` — admin-only, scoped to caller's org via `Auth/AuthContext`
- Cross-org reads → 404; `password_hash` never serialized
- **Decision:** admin self-service scoped by token `org` claim; URL-addressed OrgResolverMiddleware deferred

**Phase 1 — User write: ✅ complete** (Issue #41 / PR #42)

- `POST/PATCH/DELETE /admin/users` — password hashing, org forced to caller, cross-org → 404, superadmin not assignable (422), self-delete (409), email conflict (409)
- User management complete (CRUD + tenant isolation + escalation prevention)

**Phase 1 — Client persistence + read: ✅ complete** (Issue #43 / PR #44)

- `clients` table with soft delete; `PdoClientRepository` (reads exclude deleted); `GET /admin/clients[/{id}]` org-scoped

**Phase 1 — Client write: ✅ complete** (Issue #45 / PR #46)

- `POST/PATCH/DELETE /admin/clients` — org-scoped, soft delete, buyer `registration_number` T+13 validated

**Phase 1 — Company settings (issuer profile): ✅ complete** (Issue #47 / PR #48)

- `GET/PUT /admin/company-settings` (upsert, one row per org); `Compliance\RegistrationNumber` single home of the T+13 rule

**Phase 1 — Tax calculation engine: ✅ complete** (Issue #49 / PR #50)

- `LineItem\TaxCalculator` (pure, integer-only): round **once per rate** half-up (ADR 0004); subtotal/tax/total + per-rate breakdown

**Phase 2 — Admin UI scaffold (縦スライス): 🔄 in progress** (Issue #85)

- `frontend/`（React 19 + TS strict + Vite 7 + Tailwind v4 + TanStack Query v5 + RHF/Zod + Storybook 9 + Vitest/MSW）を規約準拠で新規構築
- レイヤード実装: `shared/{api,config,i18n,lib,ui}` → `entities/{auth,invoice}` → `features/{sign-in,list-invoices,account-menu}` → `pages/{login,layout,invoices}` → `app/{providers,router,auth-gate,root-error-boundary}`
- 認証: Bearer トークンを **in-memory** 保持（client.ts、`useSyncExternalStore` で gate 反応）。fail-closed（401→login）。localStorage/Cookie 化は ADR 待ち
- i18n は ja/en のみ、theme は `@theme` トークン（active.css 1 行差し替えで総入れ替え）、money は cents 表示整形のみ、API 型は `openapi-typescript` 生成（schema.gen.ts）
- ESLint 境界（import/no-restricted-paths）+ Tailwind 任意値禁止、`npm run check`（type-check+lint+format+test+knip+build-storybook）green、本番ビルドは `public_html/admin/`（gitignore 済み）
- テスト 9 件（mapper / list-invoices hook 3状態 / sign-in UI / account-menu hook、MSW）
- Frontend CI（`.github/workflows/frontend-ci.yml`、paths フィルタ、npm ci→check→audit、checkout/cache/setup-node @v5）
- ライブ確認: Vite dev → proxy 経由で実 API ログイン（JWT 取得）・/health OK
- 次: 一覧のページング/フィルタ、請求書詳細・作成・発行・入金の画面、Tier A 同一オリジン配信の PHP 結線

**Phase 2 — Frontend standards (本実装版): ✅ complete** (Issue #83 / PR #84)

- `docs/development/frontend-standards.md` をスタブ→本実装版へ拡張（nene-records 準拠の厳格規約: レイヤード `app→pages→features→entities→shared`、配置ゼロトレランス、データフロー、TanStack/RHF+Zod/Tailwind v4 トークン/Storybook/Vitest+MSW、ESLint 境界）
- 本製品調整を明記: **ja/en のみ（ADR 0005）**、法定請求書は常に日本語、snake_case 保持、money=integer cents・税率=bps、ビルド出力 `public_html/admin/`（Tier A 同一オリジン）、**Bearer トークンは既定 in-memory**（localStorage/Cookie 化は ADR 必須）、RBAC は UX のみ
- `docs/review/frontend.md` を本実装版チェックリストへ
- 次 PR で `frontend/` スキャフォールド（configs + app shell + shared/api + theme + i18n + invoice entity + list-invoices feature + login、`npm run check` green）

**Phase 0+ — ADR 0003 dual deployment: ✅ complete** (Issue #7 / PR #82)

- `docs/adr/0003-dual-deployment-tiers.md`（status: accepted）— Tier A 共有ホスティング（ZIP + web installer、MySQL、same-origin admin、CLI/daemon/root 不可）と Tier B Docker Compose を**単一コードベース**で両立する決定を正式化
- 共有不変条件: 本番は MySQL（SQLite はテストのみ）、env ベース設定、ドメインロジックに tier 分岐なし、PHP 8.4 floor
- 制約: root/常駐/cron/特殊拡張に依存する機能は Tier B 限定かつ任意; ZIP は vendor/ と admin アセットを同梱; installer は Phinx 移行と歩調を合わせる
- roadmap Phase 0 の ADR 0003 マーカーを ✅ に更新

**Phase 0+ — Backend CI: ✅ complete** (Issue #6 / PR #79, Node 24 対応 #80 / PR #81)

- `.github/workflows/backend-ci.yml` — push/PR to `main` で `composer check`（test + analyse + cs + openapi + mcp）を実行。初回ラン green 確認済み
- PHP 8.4（`shivammathur/setup-php@v2`）、`actions/checkout@v5` / `actions/cache@v5`（Node 24 対応）、Composer キャッシュ、NENE2 は Packagist 取得（clone 不要）

- `.github/workflows/backend-ci.yml` — push/PR to `main` で `composer check`（test + analyse + cs + openapi + mcp）を実行
- PHP 8.4（`shivammathur/setup-php`, ext pdo/pdo_sqlite）、Composer キャッシュ、`composer install`（NENE2 は Packagist から取得）
- NENE2 clone 不要（reference の nene-records は path 依存だが本リポジトリは Packagist 依存）
- `cp .env.example .env` + `mkdir -p var` は防御的（テストは phpunit が env を強制するため本来不要）

**Phase 0+ — OpenAPI + MCP catalog: ✅ complete** (Issue #5 / PR #78)

- `docs/openapi/openapi.yaml` (3.1.0) — 全 31 オペレーションを網羅（System/Auth/Audit/Organizations/Users/CompanySettings/Clients/Quotes/Invoices/Payments）、再利用 schema・parameters・Problem Details responses・examples
- `docs/mcp/tools.json`（read 専用 15 tools; mutating は非公開）+ `tools/generate-mcp-tools.php`（再生成可能）
- `tools/validate-openapi.php`（$ref 解決検証）; MCP は NENE2 `validate-mcp-tools.php` で検証（tool source↔OpenAPI operation, responseSchemaRef↔200 schema）
- composer: `openapi` / `mcp` / `mcp:generate` を追加し、`check` に `@openapi` `@mcp` を組み込み（CI ゲート化）
- `tests/OpenApi/OpenApiContractTest`: operationId 集合が実装エンドポイントと一致 / 一意 / well-formed / `{id}` パラメータ宣言 / MCP↔spec 整合
- dev 依存に `symfony/yaml ^8.1` を追加（YAML パース）
- フォロー: `public_html/openapi.php`（HTTP 配信）、ランタイム応答↔example 突合テスト

**Phase 1 — Audit read API (監査ログ参照): ✅ complete** (Issue #76 / PR #77)

- `GET /admin/audit-logs?limit&offset` — org-scoped audit trail, newest first (id DESC)
- Access: `Capability::ManageUsers`（**admin / superadmin only**）— 監査証跡は管理者オーバーサイトで、billing オペレーター（member/viewer）には非開示
- Response: `items`（id, actor_user_id, organization_id, action, entity_type, entity_id, before, after, created_at）+ `total` / `limit` / `offset`
- Reuses existing `AuditLogRepositoryInterface::findByOrganization` / `countByOrganization`（read-only; no new table）
- operationId `listAuditLogs` registered; `/admin/audit-logs` → ManageUsers added to CapabilityResolver
- Tested: org isolation + newest-first + pagination + empty; CapabilityResolver mapping; full DI boot (HealthEndpointTest)

**Phase 1 — Invoice direct create (見積を介さない請求書作成): ✅ complete** (Issue #74 / PR #75)

- `POST /admin/invoices` {client_id, line_items[], notes?} — creates a draft invoice directly (no quote)
- Same orchestration as quote create: client in-org validation + per-line checks + `TaxCalculator` (round once per rate, ADR 0004)
- 税率は 10%/8% のみ（§3）; cross-org client / empty lines / bad rate → 422 `validation-failed`
- status `draft`, `invoice_number` null（採番は発行時）, `quote_id` null; lines via `replaceForParent`; `invoice.created` audit
- operationId `createInvoice`（既登録）; reuses `InvoiceValidationException`
- Tested: create (subtotal 2000 / tax 200 / total 2200, 2 lines, no number) / cross-org client→422 / empty→422 / bad rate→422; full DI boot (HealthEndpointTest)

**Phase 1 — Payments (入金記録): ✅ complete** (Issue #72 / PR #73)

- `POST /admin/invoices/{id}/payments` {amount_cents, paid_at?, method?, note?} — records a payment, advances the invoice: partial → `partially_paid`, full → `paid`
- `GET /admin/invoices/{id}/payments` — payment list + running `total_paid_cents`（org スコープ）
- Compliance gates: integer cents only (ADR 0004); only **issued / partially_paid** invoices accept payments (draft / paid → 422 `validation-failed`); non-positive amount → 422; over-payment (recorded total > invoice total) → 422
- `payment.recorded` audit with before/after invoice status (ADR 0008); cross-org → 404
- `payments` table (Phinx + SQLite snapshot) + `Payment` entity + `PdoPaymentRepository`
- Terminology: operationId `recordPayment` registered (was speculative `createPayment`)
- Tested: partial→partially_paid / full→paid / cumulative→paid / over-payment→422 / non-positive→422 / draft→422 / paid→422 / cross-org→404; Pdo repo (sum/order); full DI boot (HealthEndpointTest)

**Phase 1 — Invoice issue (INV 採番 + 適格請求書検証): ✅ complete** (Issue #69 / PR #71)

- `POST /admin/invoices/{id}/issue` {qualified?:bool=true, due_at?} — draft → issued
- Compliance gates (accounting-compliance §2/§4): only a **draft** can be issued (issued docs immutable → 422 `validation-failed`); a **qualified** invoice requires the issuer registration number in company settings (→ 422 `qualified-invoice-incomplete`); no line items → 422
- Allocates `INV-YYYY-NNN` (DocumentNumberGenerator) on issue; sets status/issued_at/due_at/is_qualified_invoice; `invoice.issued` audit (before/after)
- Tested: qualified issue (number assigned, qualified true), non-qualified issue w/o registration, qualified-without-registration → reject, non-draft → reject, no-lines → reject, cross-org → 404; full DI boot verified (HealthEndpointTest)

**Phase 1 — Invoice convert + list/get: ✅ complete** (Issue #67 / PR #68)

- `POST /admin/quotes/{id}/convert` (accepted quote → draft invoice; copies client/totals/line_items, links quote_id, `invoice.created` audit; non-accepted → 422)
- `GET /admin/invoices`, `GET /admin/invoices/{id}` (org-scoped, +line_items)
- Verified live: convert-before-accept 422, accepted→draft invoice (total 2180, 2 lines, no number yet), list/get, audit trail

**Phase 1 — Invoice persistence: ✅ complete** (Issue #65 / PR #66)

- `invoices` table (qualified flag, totals, soft delete, nullable invoice_number until issued, unique org+number) + `Invoice` entity + `InvoiceStatus` enum (draft/issued/partially_paid/paid) + `PdoInvoiceRepository`
- Drafts have no number (multiple NULLs allowed); issue assigns number + qualified flag; org-scoped reads
- Tested on SQLite (draft save, multi-null, issue, list/count, soft delete)

**Phase 1 — Quote status transitions: ✅ complete** (Issue #63 / PR #64)

- `QuoteStatus::canTransitionTo` (draft→sent→accepted/rejected/expired; terminal states blocked)
- `PATCH /admin/quotes/{id}` {status} → 422 `invalid-state-transition` on illegal moves; draft→sent sets issued_at; `quote.status_changed` audit
- Verified live: sent/accepted ok, accepted→rejected 422, garbage 422, audit trail

**Phase 1 — Quote create/list/get: ✅ complete** (Issue #61 / PR #62)

- `POST/GET /admin/quotes`, `GET /admin/quotes/{id}` — create orchestrates client validation + `TaxCalculator` + `DocumentNumberGenerator` (EST-YYYY-NNN) + line items + `quote.created` audit
- Allowed tax rates 10%/8% (accounting-compliance §3); cross-org client / empty lines / bad rate → 422
- Verified live: create 201 (subtotal 2000 / tax 180 / total 2180, EST-2026-001, 2 lines), get/list, audit row
- Next: status transitions (draft→sent→accepted/rejected/expired)

**Phase 1 — Quote persistence: ✅ complete** (Issue #59 / PR #60)

- `quotes` table (totals columns, soft delete, unique org+quote_number) + `Quote` entity + `QuoteStatus` enum + `PdoQuoteRepository`
- Org-scoped reads exclude soft-deleted; tested on SQLite (save/list/count/update/soft-delete)
- Line items stay separate (`line_items`); the create use case orchestrates header + lines + numbering + tax + audit (next PR)

**Phase 1 — Line items persistence: ✅ complete** (Issue #57 / PR #58)

- `line_items` (polymorphic `parent_type`+`parent_id`) + `PdoLineItemRepository` (findByParent ordered / replaceForParent / deleteForParent)
- `LineItem` entity + `LineItemParent` enum; `LineItemResponse` (line_subtotal_cents only, no per-line tax per ADR 0004)
- Tenant scoping via the parent (no org_id on line_items); tested on SQLite (order/replace/isolation/delete)
- Quotes/invoices attach lines via `replaceForParent`

**Phase 1 — Document numbering: ✅ complete** (Issue #55 / PR #56)

- `document_sequences` (org × doc_type × year, unique) + `DocumentNumberGenerator` → `EST-2026-001` / `INV-2026-001`
- Atomic allocation (UPDATE+1 / INSERT fallback on unique conflict); per-org/type/year isolation with yearly reset
- Tested on SQLite; concurrency-safe locking is a documented follow-up (like audit transactionality)
- Quotes/invoices will use this for numbers

**Phase 1 — Audit logging: ✅ foundation + full platform coverage** (Issue #51 / PR #52, Issue #53 / PR #54)

- `audit_logs` + `Audit\AuditRecorder` (ADR 0008): actor / org / action / entity / before & after sanitized snapshots
- **Integrated into every mutating operation**: Client, Organization (create/delete), User (create/update/delete), CompanySettings (upsert → created/updated)
- Verified live: all write actions record rows with the correct actor; `password_hash` never logged
- **Read API added** (Issue #76): `GET /admin/audit-logs` (admin oversight; see Audit read API above)
- Limitation (ADR 0008): synchronous best-effort recording, not yet in the mutation's DB transaction (planned)
- Follow-up: transactional recording; richer audit filters (by entity_type / actor / date range)

## Handoff Notes

- Namespace: `NeneInvoice\`
- Problem Details base: `https://nene-invoice.dev/problems/`
- **Compliance (binding):** `docs/explanation/accounting-compliance.md` — zero deviations; deviation needs ADR + 税理士 sign-off
- **Terminology (single source of truth):** `docs/explanation/terminology.md` — identifiers match exactly; typos block merge
- **Multi-tenant (binding):** ADR 0006 — `organization_id` on every tenant-scoped table/query; superadmin cross-tenant only; org resolution default `single`
- Roles: `superadmin` (orgs) / `admin` (users + settings) / `member` (billing) / `viewer` (read); reference impl = nene-records `src/Auth/`, `src/Organization/`
- Money: integer cents (smallest currency unit; JPY ¥1 = 1 cent); tax: basis points (1000 = 10%)
- Tax rounding: once per tax rate per document, half-up — ADR 0004
- Qualified invoice: validate `T` + 13 digit registration number at API layer (syntax only)
- UI locale: ja + en only — ADR 0005 (not multilingual)
- Sibling boundary: ADR 0002 — HTTP only

## Phase 2 — Admin UI + PDF: ✅ complete (Issue #193–#195 / PR #196–#198)

- **見積書 PDF** (`GET /admin/quotes/{id}/pdf`) — mPDF 日本様式レイアウト、ViewQuote にダウンロードボタン
- **ユーザー管理 UI** — `/users` 一覧・作成・編集・削除（フロントエンド、バックエンド API は既存）
- **請求書メール送信** (`POST /admin/invoices/{id}/send-email`) — PHPMailer + SMTP、クライアントへ PDF 添付送信
- **Mailpit 開発コンテナ** (`docker-compose.yml`) — SMTP :1025 / Web UI :8025

## Phase 3 — Tier A 共有ホスティング: ✅ complete

- Web インストーラー (`public_html/install.php`) — DB 既存データ二重ガード済み（セキュリティ診断 F-1 対応）
- リリース ZIP ビルドスクリプト (`tools/build-release.sh`)
- 運用ガイド日本語 (`docs/operator-guide-ja.md`)
- 同一オリジン SPA 配信 (`public_html/admin/`)、`openapi.php` HTTP 配信

## セキュリティ診断: ✅ complete (2026-05-31)

- Round 1 + Round 2 実施済み (`docs/security/`)
- アプリ層の全指摘（F-1〜F-6, R2-1〜R2-6）を修正マージ済み（PR #178〜#190）
- 残件はフレームワーク/インフラ責務として文書化（JWT verifier exp 必須化、鍵分離等）

## 直近の追加実装（Issue #201 以降 / 2026-06）

Phase 1–3 後の運用・UX 強化と Phase 4 着手分（すべて merged）:

- **CSV エクスポート（#201/#202）** — 請求書・入金の会計ソフト向け出力（UTF-8 BOM）。
- **ダッシュボード集計強化（#210/#211）** — 当月入金額・前月比・売掛金エイジング。
- **案C「高密度オペ」リデザイン全画面適用（#209/#212〜#242）** — デザインシステム、ログインのスプリットスクリーン化、ブランド/favicon 整備、モバイルのテーブルカード化＋ボトムナビ、フォーム/テーブルの最終仕様準拠。
- **監査ログ UI（#222〜#244）** — 送付・DLトークン発行の記録、閲覧画面（フィルタ）、会計用語＋日英表示、操作者をメール表示、CSV エクスポート。
- **言語切替 UI（#231/#233/#234）** — 日本語/English のセグメント切替＋localStorage 永続化（ja/en は従来から対応、UI を追加）。
- **PDF 日本語文字化け修正（#245/#246）** — mPDF のフォント上書き解消＋合計欄のリテラル表示バグ修正。
- **一覧の検索・フィルタ・ソート（#247〜#252）** — 請求書/見積書（番号・取引先名検索、状態・期限・金額レンジ、全カラムソート）、取引先（名称・担当者・メール・登録番号検索＋ソート）。一覧の取引先 ID 数字表示も名前表示に修正。
- **vitest v4 更新（#226/#227）** — critical CVE（GHSA-5xrq-8626-4rwp）解消。

## Next steps（Phase 4 残り）

Phase 1–3・セキュリティ診断・上記 UX 強化は完了。Phase 4 の残り：

- **NeNe Records 商品カタログ連携** — 明細行への商品インポート
- **NeNe Concierge webhook** — リード → 取引先/見積下書き自動生成
- **決済ゲートウェイ連携** — Stripe 等（任意）
- **ユーザー一覧の検索/ソート**（任意・通常少人数のため未対応）

### 技術的負債（既知・低優先）

- **AuditRecorder のトランザクション外記録** (ADR 0008): ミューテーションの DB トランザクションに監査を統合。NENE2 フレームワーク側のトランザクション API 整備が前提。
- **JWT `exp` 必須化・人/サービス鍵分離**: `nene2` vendor 責務。OrgGuard 側の多層防御は強化済み。
