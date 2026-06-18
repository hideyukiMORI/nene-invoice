---
# Qiita 投稿用メタ（リポジトリ内メモ — Qiita には貼らない）
title: "自己ホストで適格請求書を発行する — NeNe Invoice を Docker で試す"
tags: ["Docker", "OSS", "請求書", "インボイス制度", "PHP"]
publish_target: "2026-06-17 Tue 09:00 JST"
related_issue: "#424"
related_release: "#425"
prerequisite: null
---

## はじめに

インボイス制度になってから、**適格請求書**の要件や会計 SaaS の月額、**請求データを外部に預けること**に不安を感じている中小企業は少なくありません。

**[NeNe Invoice](https://github.com/hideyukiMORI/nene-invoice)** は、見積・請求・入金を **自社サーバー上** でまとめられるオープンソース（MIT）です。Docker でも共有ホスティングでも動かせます。適格請求書形式の PDF をサーバー側で生成し、マルチテナント対応の管理画面（日本語 / 英語）から操作できます。

> **誰向けの記事か** — 経理・総務の方が「自社で置けるか」を試したいとき、またはエンジニアが社内検証するときの **ハンズオン** です。フレームワークの設計論や MCP の深掘りは別記事に譲ります（基盤は [NENE2](https://github.com/hideyukiMORI/NENE2)）。

---

## NeNe Invoice でできること（この記事の範囲）

| 項目 | 内容 |
| --- | --- |
| 見積・請求 | 取引先マスタ、明細、税率（標準 10% / 軽減 8%） |
| 適格請求書 PDF | 登録番号・税率別合計など、制度上の項目をサーバー生成 |
| 入金管理 | 一部入金・完済ステータス |
| データの置き場所 | 自社 MySQL（本記事は Docker 上の MySQL） |
| 英語 UI | **日本国内で事業する外国人オペレーター**向け（法定 PDF は日本語のまま） |

**しないこと** — 総勘定元帳、給与、在庫 POS など本格会計ソフトの代替ではありません（[Non-goals](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/explanation/product-vision.md#non-goals)）。

---

## 1. Docker で起動する

ホストに PHP や Node を入れなくてよい方法です（README の Option A）。

```bash
git clone https://github.com/hideyukiMORI/nene-invoice.git
cd nene-invoice
docker compose up -d --build
```

起動後:

| 用途 | URL |
| --- | --- |
| 管理画面 + API | http://localhost:8510/admin/ |
| API ヘルスチェック | http://localhost:8510/health |
| Mailpit（送信メール確認） | http://localhost:8585 |
| phpMyAdmin（任意） | http://localhost:8581 |

ヘルスチェック:

```bash
curl -sS http://localhost:8510/health
```

`"status":"ok"` と `"database":"ok"` が返れば準備完了です。

初回起動時、コンテナ内でマイグレーションと **開発用サンプルデータ**（取引先・見積・請求・入金）が自動投入されます。ログイン情報は次のとおりです。

| 項目 | 値 |
| --- | --- |
| メール | `admin@example.com` |
| パスワード | `password123` |

> 本番では必ず強いパスワードと `NENE2_LOCAL_JWT_SECRET` を設定してください（[運用ガイド](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/operator-guide-ja.md)）。

---

## 2. 管理画面にログインする

1. ブラウザで http://localhost:8510/admin/ を開く
2. 上記アカウントでサインイン
3. ダッシュボードに **未収・期限超過・今月の入金** などのサマリーが表示されます

画面右上の言語切替で **English** にできます。これはグローバル向け多言語化ではなく、**日本に拠点を置きながら英語 UI を好む担当者**向けです。発行する適格請求書 PDF の法定記載は **日本語** です（[ADR 0005](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/adr/0005-bilingual-ja-en-scope.md)）。

---

## 3. サンプルの適格請求書 PDF を確認する

サンプルデータにはすでに **発行済みの適格請求書** が入っています。新規作成の前に、制度対応 PDF の見え方を確認するのが早いです。

1. 左ナビの **「請求書」** を開く
2. 一覧から **INV-2026-106**（メモ: 適格請求書・軽減税率混在）を開く
3. **「PDF をダウンロード」** をクリック

PDF には次が含まれます（抜粋）:

- 発行者名・住所（会社設定）
- **登録番号**（サンプル: `T1234567890123`）
- 税率ごとの課税対象額・消費税額・合計
- 取引先情報

登録番号は **会社設定** で変更できます（設定 → 登録番号・振込先）。本番では国税庁に登録した番号を入力してください。アプリは形式チェックまでで、番号の実在確認は行いません。

---

## 4. 見積から請求書を発行する（新規フロー）

ゼロから1件通す最短ルートです。詳細は [運用ガイド（日本語）](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/operator-guide-ja.md) と同じ手順です。

### 4-1. 取引先（既存で足りる場合はスキップ可）

1. **「取引先」→「取引先を作成」**
2. 社名・メール・請求先住所を入力して保存

### 4-2. 見積書

1. **「見積書」→「見積書を作成」**
2. 取引先・明細（品目・数量・単価・税率）を入力
3. **「作成する」** で下書き保存

### 4-3. 請求書へ変換 → 適格請求書として発行

1. 見積詳細で **「送付する」** → **「承認する」** → **「請求書に変換する」**
2. 生成された請求書を開き、内容を確認
3. **「発行する（適格請求書）」** → 確認ダイアログで確定

発行後は請求書番号（`INV-YYYY-NNN`）が採番され、**内容は変更不可** になります（会計上の意図的な制約）。PDF の再ダウンロードや、クライアント向け共有 URL の発行が可能です。

### 4-4. 入金を記録する（任意）

発行済み請求書の詳細から、入金額・日付・方法を記録できます。ダッシュボードの未収・超過表示に反映されます。

---

## 5. セキュリティについて（要約）

NeNe Invoice では、リリース前に **複数ラウンドのセキュリティ自己診断**（OWASP 観点・テナント分離・認可・フロントエンド・レッドチーム）を実施し、指摘は修正済みです。レポート概要はリポジトリ内に公開しています。

- [セキュリティ診断 README](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/security/README.md)

> 自己診断は品質向上のためのものです。**税務・会計の適合性**は別途、自社の業務要件と専門家の判断で確認してください（[コンプライアンス方針](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/explanation/accounting-compliance.md)）。

---

## 6. 共有ホスティング（Tier A）について

Docker 以外に、**エックスサーバー等の共有ホスト** 向け ZIP インストーラーも用意しています。`install.php` から DB と管理者を作成し、同じオリジンで管理画面を配信する構成です。手順は [運用ガイド（日本語）](https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/operator-guide-ja.md) を参照してください。

---

## NeNe ファミリーとの関係（1行）

請求の正本は **NeNe Invoice** です。入金消込は兄弟アプリ **[NeNe Clear](https://github.com/hideyukiMORI/nene-clear)** が Invoice API を参照します。別 DB・HTTP 連携のみで、1つの巨大 SaaS にはしません。

---

## まとめ

- **1 コマンド** で API + 管理 UI + DB が立ち上がる
- **適格請求書 PDF** を自社サーバーから発行できる
- **日本語 UI が主**、英語 UI は国内ビジネスの補助
- 本番運用は共有ホスト ZIP も選択肢

まずは Docker で触って、自社に置けるかどうかを判断するのがおすすめです。

---

## リンク

| 種類 | URL |
| --- | --- |
| リポジトリ | https://github.com/hideyukiMORI/nene-invoice |
| クイックスタート | https://github.com/hideyukiMORI/nene-invoice#quick-start |
| 運用ガイド（日本語） | https://github.com/hideyukiMORI/nene-invoice/blob/main/docs/operator-guide-ja.md |
| ポートフォリオ一覧 | https://github.com/hideyukiMORI |

フィードバックは GitHub Issues へ歓迎します。記事に関する Issue: [#424](https://github.com/hideyukiMORI/nene-invoice/issues/424)
