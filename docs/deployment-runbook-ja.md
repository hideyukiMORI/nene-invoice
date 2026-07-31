# デプロイ runbook（増分デプロイ・共有ホスティング）

すでに稼働している NeNe Invoice へ、`main` の変更を**増分で反映**するための手順書。

- **対象**: 共有ホスティング（Tier A）に設置済みのインスタンスを、自分で運用している人。
- **対象外**: 初回設置（→ [`operator-guide-ja.md`](./operator-guide-ja.md)）、Docker/VPS（Tier B）での再デプロイ。
- **原則**: **DB とマイグレーションに触らない反映は、ファイルの差し戻しだけで即座に元へ戻せる。** この runbook は
  その性質を壊さない順序で書かれている。壊す操作（マイグレーション）は §7 に隔離した。

> 本書はホスト非依存で書いてある。`<deploy-host>`（ssh の接続先エイリアス）と `<app-root>`
> （本番のアプリケーションルート＝ドキュメントルート `public_html/` の 1 階層上）は自分の環境の値に読み替える。

---

## 1. 前提

| 項目 | 要件 |
| --- | --- |
| 母艦（デプロイ元） | PHP 8.4 / Composer / Node 22 以上 |
| 接続 | `ssh <deploy-host>` が鍵認証で通ること（パスワード入力を挟まない＝`rsync` が回る） |
| 本番のレイアウト | `<app-root>/src`・`<app-root>/vendor`・`<app-root>/public_html/`（うち SPA は `public_html/admin/`） |
| 母艦の状態 | `main` が origin と同期・**working tree クリーン**・CI 緑 |

**絶対に触らないもの**（本 runbook のどの手順にも登場しない）:

- `.env` — 本番の資格情報。母艦側の値で上書きしない。
- `var/` — ログ・スタンプ・レート制限の実データ。
- `public_html/` 直下（`index.php` / `.htaccess` / `openapi.php`）— 変更が必要なら個別に判断する。

---

## 2. 何を反映するかを先に確定する

前回反映した SHA から `main` までの差分を、**反映物の種別ごと**に仕分ける。

```bash
git log --oneline <前回の SHA>..main
git diff --stat <前回の SHA>..main
```

| 差分の場所 | 反映物 | 手順 |
| --- | --- | --- |
| `src/`（backend） | 実変更ファイルのみ | §4 |
| `composer.lock`（フレームワーク等の版差） | `vendor/` | §4（`--delete` つき） |
| `frontend/` | 再ビルドした `public_html/admin/` 一式 | §5 |
| `database/migrations/` | スキーマ変更 | **§7**（ロールバックの性質が変わる） |

**種別が分かったら、反映しないものは触らない。** 「ついでに全部同期」は、`.env` や `var/` を巻き込む事故の入口になる。

### 差分の全量を、転送前に数字で確定する

`rsync` の**チェックサム・ドライラン**で「実際に動くファイル」を数える。ここで出た数が想定と合わない場合、
仕分けを間違えている。

```bash
rsync -az --dry-run --itemize-changes --checksum \
  public_html/admin/ <deploy-host>:<app-root>/public_html/admin/
```

出力の読み方: `<f+++++++++` = 新規追加 / `<f..t......` 等 = 内容差あり / `*deleting` = `--delete` 時に消える対象。

---

## 3. 事前実測（preflight）— **本番と `main` の差を、仮定せずに測る**

**反映後に「変わったこと」を証明するために、変わる前を測っておく。** ここを飛ばすと、検証が
「たぶん動いている」で終わる。

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<公開URL>/health          # 期待: 200
curl -s https://<公開URL>/admin/ | grep -oE 'index-[A-Za-z0-9_-]+\.(js|css)' | sort -u
```

### 🔴 「前回のデプロイ以降の差分」を、記録から推測しない

前回の反映内容が記録に残っていても、**それが本番の実体である保証はない**。反映が部分的に終わっていたり、
別の経路で更新されていたりする。**本番そのものを測る。**

```bash
# backend: 実体差のあるファイルを列挙（転送はしない）
rsync -az --dry-run --itemize-changes --checksum src/ <deploy-host>:<app-root>/src/

# 依存: フレームワーク等の版が動いていないか
md5sum composer.lock
ssh <deploy-host> 'md5sum <app-root>/composer.lock'

# スキーマ: 本番に無い migration がないか（あるなら §7 の工事になる）
ls database/migrations
ssh <deploy-host> 'ls <app-root>/database/migrations'
```

**この 3 つが「差分なし」でない限り、フロントだけの反映は安全ではない。** フロントの変更が
backend の新しい振る舞いを前提にしている場合、**フロントだけ出すと前提を追い越す**（§8 参照）。

> この節は、実際にそれで失敗したから存在する。「backend は差分ほぼ無し」という**記録上の前提**を
> 測らずに引き継いでフロントだけを反映し、有効化した機能が要求する backend 修正が本番に
> 入っていなかった。**preflight を回していれば反映前に分かった。**

---

## 4. backend / vendor の反映

```bash
# 母艦: 実変更ファイルだけを名指しで送る
rsync -c <変更ファイル> <deploy-host>:<app-root>/<同じ相対パス>

# vendor を入れ替える場合（composer.lock に差があるときだけ）
# 母艦のクリーンな stage で: composer install --no-dev --optimize-autoloader
rsync -azc --delete <stage>/vendor/ <deploy-host>:<app-root>/vendor/
```

- **`-c`（チェックサム比較）は必須。** `git archive` や再ビルドは mtime を潰すので、`-a` だけだと
  中身が同じファイルまで全件転送になる。
- 上書きする前に、対象ファイルを `<名前>.bak-YYYYMMDD` で退避する（§6 の差し戻し先になる）。

---

## 5. SPA（`public_html/admin/`）の反映

SPA のビルド成果物は**内容ハッシュつきファイル**（`assets/index-<hash>.js` など）と、それを参照する
`index.html` の組でできている。**この 2 つを分けて送れる**ことが、この手順の要点。

### 5-1. 母艦でビルドする

```bash
cd frontend && npm ci && npm run build   # → ../public_html/admin/ を再生成
```

- ⚠️ `vite build` は `emptyOutDir: true` で **`public_html/admin/` を全消ししてから再生成**する。
  ここに手で置いたファイルがあれば消える。
- ビルドは再現的であるべきもの。**同じコミットからのビルドが以前の成果物とバイト一致すること**を
  確認しておくと、「送ろうとしている物は本当にこのコミットの物か」を後から疑わずに済む。

### 5-2. 退避してから、新資産を**先に置く**

```bash
# 退避（ロールバック元）
ssh <deploy-host> 'cd <app-root>/public_html && cp -a admin admin.bak-YYYYMMDD \
  && [ "$(find admin -type f | wc -l)" = "$(find admin.bak-YYYYMMDD -type f | wc -l)" ] \
  && echo BACKUP-OK'

# 新しい内容ハッシュ資産だけを追加（index.html は送らない・削除もしない）
rsync -az --ignore-existing --exclude='index.html' \
  public_html/admin/ <deploy-host>:<app-root>/public_html/admin/
```

**`BACKUP-OK` が出るまで次へ進まない。** 退避が取れていない状態の反映は、ロールバック手段のない反映と同じ。

この時点で公開中の `index.html` は**まだ旧ハッシュを参照している**ので、**利用者から見た挙動は何も変わらない**。
一方で新資産は URL として取得可能になっているので、**切り替え後に 404 で白画面になる経路が無いことを
先に潰せる**:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<公開URL>/admin/assets/index-<新hash>.js   # 期待: 200
curl -s https://<公開URL>/admin/ | grep -oE 'index-[A-Za-z0-9_-]+\.(js|css)' | sort -u      # 期待: 旧hash のまま
```

### 5-3. 切り替え（swap）

`index.html` を差し替えると、その瞬間から新しい資産が読まれる。古い資産の削除も同時に行う。

```bash
rsync -az --delete public_html/admin/ <deploy-host>:<app-root>/public_html/admin/
```

- 差し替えと同時に旧資産が消えるが、**切り替え前に開かれていたページ**が旧資産を再取得することは
  基本的に無い（内容ハッシュ資産はロード時に取得済み）。気になる場合は `--delete` を次回に回し、
  旧資産の削除だけ後日行ってもよい。
- **退避ディレクトリ（`admin.bak-*`）は `--delete` の対象外**（`public_html/` 直下にあり、同期対象は
  `admin/` の中だけ）。

---

## 6. 検証とロールバック

### 検証（すべて実測する・「たぶん」を残さない）

| 項目 | 期待 |
| --- | --- |
| `GET /health` | 200 |
| SPA シェル `GET /admin/` | 200 `text/html`・**参照ハッシュが新しい方に変わっている** |
| 新資産 `GET /admin/assets/index-<新hash>.{js,css}` | 200（`application/javascript` / `text/css`） |
| （デモ入口を持つ設置なら）入口 URL | 302 |
| **認証セッションの維持** | ブラウザで**リロードを 2 回**してログイン状態が保たれる |

**最後の 1 行が最も重要。** リフレッシュトークンの発行スコープを間違えると、1 回目のリロードは通り
**2 回目で強制ログアウト**する（トークン再利用検知が正しく作動してしまう）ため、**リロード 1 回では
検出できない**。

### セッション維持の検証を、手作業でなく実行可能な形で回す

ブラウザで 2 回リロードするのと同じ経路を HTTP で辿れる。**証跡が残り、見落としが起きない。**

```bash
JAR=$(mktemp)
# 1. 入場（テナントの入口）— cookie を受け取る
curl -s -o /dev/null -c "$JAR" https://<公開URL>/<入口>
# 2. cookie の Path を見る。テナント配下（/<slug>/…）にスコープされていること
awk 'NF && $0 !~ /^# /{print $3"\t"$6}' "$JAR"
# 3. 1 回目のリフレッシュ（＝リロード 1 回目）。Set-Cookie の Path を必ず目視する
CSRF=$(awk -v s="/<slug>/" '$6=="ni_csrf" && $3==s {print $7}' "$JAR")
curl -s -D - -o /dev/null -X POST -b "$JAR" -c "$JAR" \
  -H "X-CSRF-Token: $CSRF" https://<公開URL>/<slug>/auth/refresh | grep -iE '^HTTP/|^set-cookie'
# 4. 2 回目のリフレッシュ（＝リロード 2 回目）。ここが判定点
CSRF2=$(awk -v s="/<slug>/" '$6=="ni_csrf" && $3==s {print $7}' "$JAR")
curl -s -w '\nHTTP=%{http_code}\n' -X POST -b "$JAR" -c "$JAR" \
  -H "X-CSRF-Token: $CSRF2" https://<公開URL>/<slug>/auth/refresh
```

**合格条件は 2 つある**:

1. 手順 4 が **200**（`401 invalid-refresh-token` ならセッションが焼かれている）。
2. 手順 3 の `Set-Cookie` の **`Path` がテナント配下**（`Path=/<slug>/auth`）であること。
   ここが `Path=/auth` に落ちていると、次のリクエストでブラウザは**古い方の cookie を送り**、
   再利用検知が作動して強制ログアウトになる。**手順 3 が 200 でも、Path が違えば不合格。**

### ロールバック

```bash
ssh <deploy-host> 'cd <app-root>/public_html && rm -rf admin && mv admin.bak-YYYYMMDD admin'
# backend を反映した場合: mv <file>.bak-YYYYMMDD <file>
```

DB とマイグレーションに触っていなければ、これで即座に元の状態に戻る。**退避物は、次回デプロイで
安定を確認するまで消さない。**

---

## 7. マイグレーションを伴う場合（性質が変わる）

`database/migrations/` に差分があるデプロイは、**ファイルの差し戻しだけでは元へ戻らない**。

1. 反映前に **DB のバックアップを取得**する（これがロールバック手段になる）。
2. ファイルを反映してから `php vendor/bin/phinx migrate -c phinx.php` を実行する（ホストによっては
   `php` が未設定で `php8.4` のようにバージョン付きの名前になっている）。
3. 逆向き（`rollback`）が安全に書かれているかを、実行前に migration の実装で確認する。

**この手順を、通常の増分デプロイと同じ気軽さで実行しない。**

---

## 8. 地雷（実測で踏んだもの）

- **`vite build` は出力先を全消しする**（`emptyOutDir: true`）。`public_html/admin/` は「ビルド成果物の
  置き場」であって、手で何かを置く場所ではない。
- **`rsync -a` だけでは全件転送になる**（mtime が保存されないビルド成果物のため）。`-c` を付ける。
- **デモ入口への `GET` は、使い捨てのデモ組織を 1 件払い出す**設置がある。検証で叩いた分もレート制限を
  消費するので、入口の検証は必要最小限にとどめる（払い出された組織は TTL で自動消滅する）。
- **`index.html` と内容ハッシュ資産を同時に送る**と、切り替えの瞬間だけ「新しい HTML が、まだ届いて
  いない資産を参照する」窓が開く。§5 の「先置き → 切り替え」はこの窓を無くすための順序。
- 🔴 **フロントだけの反映が、backend の前提を追い越すことがある。** フロント側で機能を有効化する変更
  （ゲートの解除・フラグの ON）は、しばしば backend 側の修正を前提にしている。**backend が古いまま
  フロントだけ出すと、前提の無い状態で機能が有効になる。** 「フロントしか変えていない」は
  「安全である」を意味しない。§3 の preflight で backend の実体差を測ってから出す。

---

## 9. 記録

反映のたびに、少なくとも次を残す（本リポの docs ではなく、運用側の記録に）:

- 反映した **SHA**・反映物の種別・**反映前後の資産ハッシュ**
- 検証結果（§6 の表をそのまま）
- 退避物の名前（＝ロールバック手段の所在）

**手順の変更が必要になったら、この runbook を直してからデプロイする。** 手順書と実際の手順が食い違った
状態で回すと、次に読む人が実在しない手順を実行することになる。
