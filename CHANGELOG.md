# Changelog

NeNe Invoice のすべての注目すべき変更をここに記録します。
書式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に、
バージョンは [Semantic Versioning](https://semver.org/lang/ja/) に従います。

---

## [1.0.0] — 2026-07（公開リリース）

自己ホスト型の **見積・請求・入金管理** OSS（MIT）としての最初の公開リリース。
日本の SMB 向けに **適格請求書（インボイス）** に対応し、共有ホスティング（Tier A）または
Docker/VPS（Tier B）で動作する。開発フェーズ（Phase 0–3）完了＋エコシステム連携（Phase 4）の
主要機能を搭載。

### コア請求（見積 → 請求 → 入金）

- 取引先（clients）・発行者プロフィール（company settings）・文書採番（見積 QUO / 請求 INV）の管理。
- **見積の作成・状態遷移・請求書への変換**、請求書の直接作成・発行（適格請求書の法要件検証つき）。
- **入金記録**（paid / partially_paid 遷移・売掛金残高 `outstanding_cents`・過入金 422）。
- 金額はすべて **integer cents**（浮動小数点なし）、税計算エンジン（端数処理 ADR 0004）。

### 適格請求書・会計コンプライアンス

- **適格請求書（インボイス）対応 PDF**（登録番号・税率区分・端数処理）。日本語フォント埋め込み。
- 会社ロゴ（`logo_url`）を請求書・見積 PDF に描画。
- **監査ログ基盤**（ADR 0008）— 全ミューテーション記録・閲覧 UI・CSV エクスポート。
- 会計・税務コンプライアンスを拘束ルール化（`docs/explanation/accounting-compliance.md`）。

### マルチテナント・認証（ADR 0006 / 0014）

- **マルチテナント基盤**（組織＝テナント・`organization_id` スコープ・superadmin のクロステナント）。
- ロール階層（superadmin / admin / member / viewer）＋ capability による per-route 認可。
- JWT ログイン、**httpOnly refresh cookie によるサイレント再認証**（リロード後のセッション復元・
  ローテーション＋reuse 検知・CSRF 二重送信）。
- **型B マルチテナント** — superadmin による組織プロビジョニング＋顧問先 SPA を `/{slug}/` 配下で提供。

### 決済（ADR 0012 / 0013）

- **カード決済**（PAY.JP・hosted-only / SAQ-A）— 公開決済ページ・請求書ごとの決済リンク生成/失効・
  webhook ingress・ゲートウェイ設定の疎通テスト。

### 自動化・エコシステム連携

- **定期請求**（`/recurring`）— スケジュールから下書き請求書を自動生成（Tier B cron / Tier A インライン）。
- **銀行入金の自動消込**（`/bank-reconciliation`）— CSV 取込（Shift_JIS 対応）→ 名義ゆれ辞書照合 →
  スコアリング → 確認起票（税理士サインオフ済み RecordPayment 再利用・冪等）。
- **NeNe Clear 連携**（downstream consumer・入金消込/督促）— `/api/*` サービス面＋サービストークン
  （`read:invoices` / `write:payments`・組織スコープ・失効レジストリ）。契約検証済み。
- MCP ツールカタログ（read-only・OpenAPI 由来）と OpenAPI 契約（`/admin` + `/api`）。

### 管理 UI（React）

- 見積・請求・取引先・入金・ダッシュボード（当月入金・前月比・売掛金エイジング）・監査ログの管理画面。
- 一覧の検索・フィルタ・ソート、CSV エクスポート（フォーミュラインジェクション対策）。
- **日本語 / English 言語切替**、レスポンシブ（モバイルのテーブルカード化＋ボトムナビ）、
  キーボードショートカット。

### 配布・運用（Tier A / Tier B・ADR 0003 / 0015）

- **Tier A 共有ホスティング**向けの自己完結インストーラ（`public_html/install.php`）＋
  リリース ZIP ビルド（`tools/build-release.sh`）。設置後に自己削除。
- **設置場所非依存**（ADR 0015）— ルート / サブディレクトリ / サブドメインのいずれでも
  再ビルドなしで動作（install base 自動検出）。
- **Docker** による最短起動（`docker compose up` で API + 管理 UI + MySQL・マイグレーション自動）。
- インストーラを NENE2 `Nene2\Install` toolkit の consumer 化、スキーマは phinx マイグレーションが唯一の正。

### セキュリティ

- セキュリティ診断 Round 1–2 実施・アプリ層の全指摘を修正。
- IP 単位のログインスロットル、本番での JWT シークレット fail-closed、
  自己ホスト設置物への**テレメトリ非搭載**。

### 設計判断（ADR）

主要な設計判断は `docs/adr/` に記録（sibling 分離 0002・dual deployment 0003・端数処理 0004・
マルチテナント 0006・監査 0008・NeNe Clear 上流契約 0009・カード決済 0012/0013・
セッション永続化 0014・設置場所非依存 0015 ほか）。

---

> **開発中 / 設計済み（v1.0 以降）**: MFA（TOTP）、手数料差引 write-off・過入金按分（税理士ゲート）、
> 一括発行、業種テンプレート精緻化、NeNe Records カタログ取込 / Concierge webhook。
> 詳細と順序は [`docs/roadmap.md`](./docs/roadmap.md) / [`docs/todo/current.md`](./docs/todo/current.md)。
