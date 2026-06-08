# セキュリティ診断履歴

NeNe Invoice に対して実施したセキュリティ診断（認可された自己診断）の記録です。

## 診断レポート

| 日付 | レポート | 概要 |
| --- | --- | --- |
| 2026-05-31 | [Round 1](2026-05-31-assessment-round1.md) | OWASP 主要カテゴリ網羅（認証・テナント分離・認可・インジェクション・ヘッダ等） |
| 2026-05-31 | [Round 2](2026-05-31-assessment-round2.md) | 深掘り（JWT 検証内部・型混同・会計ロジック・サービストークン・PDF・状態管理）＋対応状況 |
| 2026-06-08 | [Round 3](2026-06-08-assessment-round3.md) | 全機能網羅再診断（Phase 4 の CSV インポート/エクスポート・ServiceApi・PostgreSQL 対応含む）＋対応状況 |
| 2026-06-08 | [Round 4（レッドチーム）](2026-06-08-assessment-round4-redteam.md) | 敵対的・実弾ペネトレーション（稼働インスタンスへ実攻撃）。最大長検証/float厳格化を修正 |

各レポートは Finding（深刻度／証拠／推奨）、検証済みの堅牢性、Remediation（対応 PR）を含みます。Round 2 末尾の対応状況表に、各指摘の修正 PR（#178〜#190）を記載しています。

## 再現ハーネス

[`harness/`](harness/) に、診断に使ったローカル再現環境（Docker）と道具を収めています。バックエンドをスタブせず**実アプリを起動して**叩くための最小構成です。

> ⚠️ 認可された自己所有アプリ・隔離環境での検証専用。第三者システムへの無断使用は禁止。

### 実行方法

```bash
cd docs/security/harness
cp .env.app.example .env.app
# .env.app の NENE2_LOCAL_JWT_SECRET を生成して設定:
#   php -r "echo bin2hex(random_bytes(32));"
docker compose up -d --build      # app:8590 / db:3385（既存コンテナと非衝突ポート）

# 動作確認
curl -s localhost:8590/health

# 解体
docker compose -p nene-invoice-sectest down -v
```

- `mint.php` は HMAC シークレットを使った JWT 生成ツール（alg/exp/org クレームの検証用）。
- 秘密情報（`.env.app` 実体・取得トークン・ログ）は `.gitignore` 済みでコミットしません。
