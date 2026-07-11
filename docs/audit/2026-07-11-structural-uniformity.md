# 構造統一性監査 2026-07-11 — invoice 分サマリ

- 実施日: 2026-07-11
- 対象: NeNe 4製品（nene-invoice / nene-clear / nene-deal / nene-vault）の各 main を、①NENE2 の使い方 ②認証・セッション ③マルチテナント ④インストーラ／配布 ⑤フロントエンド ⑥デモ機構 の 6 観点で横断比較した構造統一性監査の、**invoice 該当分**の収録。
- 全所見は実コード file:line で裏取り済み（読み取り専用で実施）。
- 是正 Issue: [#629](https://github.com/hideyukiMORI/nene-invoice/issues/629)・[#630](https://github.com/hideyukiMORI/nene-invoice/issues/630)・[#631](https://github.com/hideyukiMORI/nene-invoice/issues/631)（本ドキュメントの収録は #632）

---

## 1. 総評

4製品の骨格（NENE2 Router/DI/ServiceProvider・RFC9457 Problem Details・`Nene2\Demo` consumer・`Nene2\Install` コア部品・React+Vite+Tailwind 4 の核スタック・`organization_id` 行スコープ）は高いレベルで統一されており、**invoice は複数領域でフリートのリファレンス実装**と位置付けられた。一方で、invoice 固有の乖離は「一度事故って直した領域の、当時の解のまま止まっている層」（ビルドスクリプト・demo 層の細部・インストーラ後始末）に集中している。

## 2. invoice がリファレンス実装になっている点

| 領域 | 内容 | 根拠 |
|---|---|---|
| 認証セッション | **refresh セッションスタック（ADR 0014）**: opaque 14日・rotation family・reuse 検知・httpOnly cookie＋CSRF double-submit＋in-memory アクセストークン。4製品で唯一「リロード生存かつ XSS 窃取不可」を両立し、フリート標準化候補の筆頭 | `src/Auth/RefreshTokenIssuer.php`・`src/Auth/SessionCookies.php`・`frontend/src/shared/api/client.ts` |
| ベースパス | **サブディレクトリ設置対応（ADR 0015）**: `SpaShell` が `<base href>`＋`<meta app-base>` を注入し router basename 化。「1ビルドどこでも動く」のは invoice のみ | `src/Http/SpaShell.php`・`frontend/src/shared/config/app-base.ts` |
| SPA/API 境界 | PHP front controller が非 API GET にシェル配信＋公開 URL の明示除外（#620 の恒久対策）。.htaccess prefix 手書き方式（deal/vault）より route 追加漏れに強い | `public_html/index.php`・`frontend/vite.config.ts` |
| リリースビルド | **build-release.sh の allowlist 形**: allowlist staging＋Packagist 解決＋symlink 検査 fail＋SHA-256 サイドカー（#576）。4流派の中で最堅牢で、標準テンプレ候補 | `tools/build-release.sh` |
| インストーラ | `Nene2\Install` 部品の最多消費（6部品＋TenantConfigurationValidator）・ZIP アップロード取得フロー（PayloadAcquisition）・検証用 Docker 環境は invoice のみ | `public_html/install.php`・`compose.installer.yaml` |
| テナントスコープ | repo ctor への `RequestScopedHolder<int>` 注入方式（ADR 0006）が「スコープを忘れられない」fail-close 実装のスケール実証（org スコープ migration 18本） | `src/Client/PdoClientRepository.php:21` ほか全 Pdo repo |
| フロント品質装備 | unit 84＋Playwright E2E 23 spec＋Storybook＋MSW＋knip は4製品最厚 | `frontend/package.json`・`frontend/e2e/` |

## 3. 統一できている点（4/4 製品で確認・invoice も準拠）

- NENE2 Router / DI / ServiceProvider / RFC9457 Problem Details / conformance 体制（`conformance.baseline.json`）
- `Nene2\Demo` consumer（throttle 30/h・上限200＋503・TTL 3h・attempts 5・DEMO_MODE fail-close・admin seat・クエリ層に demo 特殊分岐ゼロ）
- `Nene2\Install` コア3部品（EnvironmentWriter / DatabaseSchemaApplier / ReInstallationGuard）＋public_html 配置＋3ステップウィザード＋再設置ガード
- フロント核スタック（React 19+Vite+Tailwind 4+TanStack Query+zod）と fetch 単一モジュール・X-Authorization 両ヘッダ（HETEML Authorization 剥ぎ対策）
- `organization_id` 行スコープ＋`organizations.slug` unique
- NENE2 依存が Packagist タグ固定（`^1.10`・deal と並び再現ビルド可能。clear/vault は path `@dev` で課題側）

## 4. 乖離している点（invoice 分・是正 Issue）

| # | 所見 | 根拠 | 影響度 | Issue |
|---|---|---|---|---|
| a | `tools/build-release.sh` が `NENE2_CONSTRAINT="^1.8.2"` をハードコードし composer.json `^1.10` と乖離 — リリース ZIP が repo と別の NENE2 で組まれる | `tools/build-release.sh:25` / `composer.json:8` | **即修正級** | [#629](https://github.com/hideyukiMORI/nene-invoice/issues/629) |
| b | `dist/nene-invoice-1.0.0-demo.zip`（07-08）が `Nene2\Demo` consumer 化前の旧デモ世代（zip 内に旧 `src/Demo/StartDemoHandler.php` 等を実確認） | zip listing | **高**（誤配布リスク） | [#630](https://github.com/hideyukiMORI/nene-invoice/issues/630) |
| c | demo org カウントの LIKE に ESCAPE なし（他3製品は適用済み・invoice も業務クエリでは `ESCAPE '!'` 使用） | `src/Demo/DemoServiceProvider.php:123` | 低 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |
| d | グローバル `RateLimitStorageInterface` をデモ用 `FileRateLimitStorage` で束縛（他ミドルウェア巻き添えの温床） | `src/Demo/DemoServiceProvider.php:103-108` | 低〜中 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |
| e | demo sweep の JST/UTC 回帰テストなし（UTC 明示パース自体は実装済み。clear/vault にはピン留めテストあり） | `tools/sweep-demo.php:75` / `tests/Demo/` | 中 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |
| f | インストーラのパスワード最低長 8 文字（他3製品は 12） | `public_html/install.php:535-536,1190-1197` | 低〜中 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |
| g | installer 後始末がガード残置方式（deal/vault は完了時 `@unlink(__FILE__)` 自己削除。統一方針は自己削除形。#578 と同時対応が効率的） | `public_html/install.php`（complete view） | 高 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |
| h | `organizations.custom_domain` に unique index なし（slug/external_id にはあり・vault 同スキーマにはあり。`findByCustomDomain()` がテナント解決経路） | `database/migrations/20260529120000_create_organizations_table.php` / `src/Organization/PdoOrganizationRepository.php:41-44` | 低〜中 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |
| i | org 解決に claim 方式（案C）未適用 — strategy のみで、demo は `TENANT_RESOLUTION=path` が前提（deal/vault は claim 最優先で単一ドメインのまま demo が動く） | `src/Organization/Resolution/OrgResolverMiddleware.php` / `.env.example:12-20` | 中 | [#631](https://github.com/hideyukiMORI/nene-invoice/issues/631) |

## 5. フリート横断の文脈（invoice に直接の是正なし・記録のみ）

- `AuthorizationHeaderFallback`・`FileRateLimitStorage`・`DemoBrowserErrorPage`・OrgResolver strategy 群は4製品にほぼ同一コピーが増殖しており、NENE2 上流化候補（上流化されたら invoice も consumer 化して自前実装を撤去する側）。
- invoice の refresh セッション（ADR 0014）・ベースパス（ADR 0015）・build-release allowlist 形は、他3製品への横展開／上流標準化のリファレンスとして参照される。
- テナント scoped-repo の上流化（`Nene2\Tenancy` 構想）では、invoice の holder-in-repo 方式がスケール実証、deal の `Tenancy/` パッケージが最良シードとされた。前提差分（org PK int vs ULID・claim 名 `org` vs `org_id`）の解消が必要。
