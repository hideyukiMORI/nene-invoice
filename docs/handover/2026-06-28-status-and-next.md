# 引き継ぎ書 — NeNe Invoice 現状と今後の課題（2026-06-28）

> 対象読者: 次にこのリポを触る開発者／AIエージェント。2026-06-27〜28 セッションの成果と、
> ペルソナ評価 R1→R4 を踏まえた優先課題をまとめる。状態の正本は `docs/todo/current.md`、
> 戦略の正本は `../_work/discussion-log/` ＋ `../_work/reports/2026-06-27/`。

## 0. 一行サマリ

NeNe Invoice は Phase 1–3（コア請求・管理UI・Tier A）＋ Phase 4 の多くが完成済み。本セッションで
**定期請求（ペルソナ評価の「次の一手」）を管理UIまで実装**し、**NeNe Clear との財務クラスタを実接続で検証**、
**MFA の設計を確定**した。ペルソナ R4 で「マネージド・クラウド版（NeNe Suite）の実在が自己ホストの壁を崩す
（見送り8→0）」一方「課金転換には機能完成が要る」ことが判明。**次の最優先は定期請求の実行ルート配線（#526）**。

## 1. エコシステム上の立ち位置（重要）

- **NeNe Invoice = Billing SSOT／財務クラスタの「土台」**。clear の upstream（HTTP のみ・DB分離・ADR 0002）。
- **NeNe Clear = 第一の現金の楔**（入金消込・督促 ROI）。invoice は土台。
- **NeNe Suite = マネージド・クラウド版（増幅器）**: 体験無料版／自社VPS移行設置代行／有料クラウド保証。
  invoice 自前 SaaS は作らず Suite に conform（orchestrator-not-monolith）。invoice がクラウドに乗る道 =
  **federation エピック #492–#497**。
- **料金 = M2 managed-first**（無料 self-host は信頼装置として据え置き＋managed 月額＋setup）。
  SMB 向けには「データ主権」を訴求しない（managed/安心）。会計事務所デッキにのみ主権を残す。
- 3者収束（managed-first）: 本リポのペルソナ評価／`_work` 価格パネル③／clear PR #206。

## 2. このセッションでマージ済み

| PR | 内容 |
| --- | --- |
| #498 | `organizations.external_id` 解決 `findByExternalId`（federation 第一歩・#492） |
| #501 | 全機能のバックエンド UT に境界値ケース +152（#499） |
| #502 | 登録番号バリデータ `$`→`\z`（末尾改行受理バグ・#500） |
| #519 | 定期請求 永続化レイヤ（#503） |
| #520 | 定期請求 下書き生成（due→draft・月末クランプ・冪等・#503） |
| #521 | 定期請求 CRUD ユースケース層（#503） |
| #522 | 定期請求 管理API＋OpenAPI（`/admin/recurring-invoices`・#503） |
| #523 | 定期請求 管理UI `/recurring`（一覧・作成・編集・有効/停止・削除・#503） |
| #525 | MFA（standalone TOTP）詳細設計 `docs/design/mfa-totp.md`（#524） |
| clear #215 | Clear↔Invoice 消費者側2バグ修正（nested payment / comma status）→ 契約6/6緑（clear#214） |

作成済み Issue（未着手バックログ）: epic #518（R2 改善バックログ #503–#517）、#524（MFA epic）、
**#526（P0 定期請求実行ルート）/ #527（P1 一括発行・一括メール）/ #528（P2 見積単位欄・工種内訳）**、#529（本ドキュメント）。

## 3. 稼働中／実装済み（実機 Docker :8510・seed あり）

- 認証（JWT・IP throttle・refresh cookie ADR 0014）、マルチテナント、RBAC。
- 取引先・品目・テンプレート・見積・請求・入金・ダッシュボード・監査ログ・CSV エクスポート・適格請求書 PDF・メール送信・カード決済（PAY.JP）・サービストークン。
- **定期請求**: スケジュール登録（取引先/周期/明細/初回日）→ 期日で**下書き請求書を自動生成**（税額再計算・二重生成しない）。管理UI `/recurring`。**ただし実行は手動トリガー（cron/CLI未配線=#526）**。
- **財務クラスタ**: clear（消込・督促）↔ invoice `/api/*`（read:invoices / write:payments）が契約検証済み。
  本番常時化は clear に env（`NENE_INVOICE_API_BASE_URL` / `NENE_INVOICE_BEARER_TOKEN`）設定のみ。

## 4. ペルソナ評価の結論（R1→R4・課金の鍵）

10ペルソナ（共用ホスティング利用の日本SMB会計決裁者）を4ラウンド評価。レポート: `docs/research/persona-review-2026-06-27/`（`persona-review-report.md` / `next-move-report.md` / `verify-report.md` / `full-update-review.md`）。

- **R1**: 採用0/検討2/見送り8。最大の壁＝自己ホスト運用不能（クラウド版がない）。
- **R2（次の一手投票）**: 定期請求が最小労力×最大採用増 → 実装。
- **R3（定期請求のみ）**: 採用1/検討1/見送り8。技術者1名のみ前進、母数不動。cron未配線が「肝抜け」。
- **R4（全更新提示＝クラウド実在＋定期請求＋MFA＋クラスタ＋料金）**: **採用1／検討9／見送り0／支払3**。
  - マネージド・クラウド版の実在で**「見送り→検討」は完全に崩れた（8→0、全員がクラウド実在を唯一の決め手に）**。
  - だが**新規採用・新規課金はゼロ**（採用1=据え置き、支払3=R1から同一人物）。クラウド＝検討の必要条件、採用/課金の十分条件ではない。
  - **MFA を決め手/障害に挙げた人は0/10** → 最優先でない。
  - 変換ブロッカー（課金転換の壁）＝各セグメント固有: 定期請求の実行配線、銀行の真の自動消込、一括発行、業種テンプレ（見積単位欄/源泉徴収）、価格（月¥9,800＋初期¥98k は Misoca 比で高いと複数）。

## 5. 今後の課題（優先度順）

1. **#526（P0）定期請求の実行ルート配線**（cron `tools/run-recurring.php` / Tier A リクエスト時 due）。
   R4 最多言及・完成度9割。`GenerateDueRecurringInvoicesUseCase` に呼び出し元が無い＝看板機能が自動で回らない。
2. **#505 銀行入金CSVの真の自動消込**（渡辺の支払条件・佐藤のゲート）。clear の CSV取込＋人手確認を自動化へ。
3. **#527（P1）一括発行・一括メール**（会計事務所セグメント ¥49,800 解錠・岡田）。
4. **業種テンプレ**: #528（見積の単位欄/小数数量/工種内訳・中村）、#513（源泉徴収・清水）。
5. **MFA（standalone TOTP）#524**（設計済み・ADR 0019 から）。R4 では非最優先だがエンタープライズ信頼に必要。裏で進行可。
6. **federation エピック #493–#497**（NENE_SUITE_MODE → JWKS 検証+JIT → join/leave+組織UI → `/machine/health` → candidate-db preflight）＝ invoice を Suite マネージドクラウドに載せる道。`/machine/health`(#496) は Suite が既に参照。
7. **定期請求 auto-issue（採番・適格請求書）**: accounting-compliance §の拘束対象。**ADR＋税理士サインオフ必須**（現状は「下書き自動生成→人が確認して発行」で安全運用）。
8. 旧 Phase 4 残: NeNe Records カタログ取込・NeNe Concierge webhook・認証セッション残（#464/#465）。

## 6. リスク・注意点（必読）

- **コンプライアンス（非交渉）**: 請求・税・採番・PDF・保存・**入金/消込**に関わる変更は `docs/explanation/accounting-compliance.md` ＋ `docs/review/compliance.md` 確認必須。定期請求の **auto-issue は税理士ゲート**。境界値は ADR 0004（税率別丸め）厳守。
- **スキーマパリティ**: 新テーブルは Phinx ＋ SQLite snapshot ＋ installer `database/schema/mysql/schema.sql` の3点同期（`tests/Installer/SchemaParityTest`）。MFA 3テーブルもこれに従う。
- **OpenAPI 契約ゲート**: 新 `/admin` エンドポイントは `docs/openapi/openapi.yaml` ＋ `OpenApiContractTest` の operationId 正準集合 ＋ terminology §5 を必ず更新。
- **用語レジストリ**: 全識別子は `docs/explanation/terminology.md` と完全一致（同一PRで更新）。
- **並行作業の衝突**: clear リポは別セッションが MFA を作業中（未コミット WIP あり）。clear を触る際は1ファイル単位でステージし WIP を巻き込まない（本セッションの clear#215 はこれを徹底）。
- **dev データのドリフト**: 契約検証の初回（修正前）に INV-2026-108 へ1円入金が残存（outstanding 119,999）。dev seed の軽微なドリフト。必要なら void。
- **価格の壁**: R4 で「月¥9,800＋初期¥98k は Misoca(月800)/freee 比で高い」が複数。課金は ROI を見せられるか次第（消込/督促の工数削減）。

## 7. 運用メモ

- 起動: `docker compose up -d --build` → http://localhost:8510（`admin@example.com` / `password123`）。本セッションでは起動中の可能性（`docker compose down` で停止）。
- サービストークン発行: `docker compose exec -T app php tools/issue-service-token.php --org=1 --scopes=read:invoices,write:payments`。
- clear 契約テスト: `NENE_INVOICE_API_BASE_URL=http://localhost:8510 NENE_INVOICE_BEARER_TOKEN=<token> NENE_INVOICE_CONTRACT_ORG=1 vendor/bin/phpunit -c <clear>/phpunit.xml <clear>/tests/Contract/InvoiceUpstreamContractTest.php`。
- 品質ゲート: backend `composer check`（PHPUnit/PHPStan/cs/openapi/mcp）、frontend `npm run check`（FixedClock=2026-06-06 に注意）。

## 8. 参照ポインタ

- ペルソナ評価レポート群: `docs/research/persona-review-2026-06-27/`
- MFA 設計: `docs/design/mfa-totp.md`（Suite `../nene-suite/docs/adr/0025-mfa-step-up-authentication.md` 準拠）
- federation: Suite `../nene-suite/docs/adr/0012-federation-participation-contract.md`、Invoice ADR 0016
- 戦略 SSOT: `../_work/discussion-log/2026-06-27.md` ＋ `../_work/reports/2026-06-27/`（90day-plan-v2 / pricing-model-eval ほか）
- ロードマップ: `docs/roadmap.md` Phase 4 ／ マイルストーン: `docs/milestones/2026-06-financial-cluster-and-recurring.md`

## 9. 直近の推奨アクション

1. **#526（定期請求の実行ルート配線）** に着手 — 最小工数で看板機能を「本物」にし、R4 の検討9名の変換土台を作る。
2. 続けて **#505 自動消込 → #527 一括発行 → #528/#513 業種テンプレ**（課金転換の順）。
3. MFA（#524）は ADR 0019 起票後、段階実装（設計書のフェーズ計画どおり）。最優先ではないが信頼上は必要。
4. federation（#493–）は Suite マネージド提供の前提として並行検討（`/machine/health` #496 は Suite が待っている）。
