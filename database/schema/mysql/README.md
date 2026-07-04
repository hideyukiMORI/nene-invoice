# MySQL スキーマ参照

スキーマの正は `database/migrations/`（Tier A web installer も phinx＝
`Nene2\Install\DatabaseSchemaApplier` で migration を直接適用する。#562 決定A）。

かつてここにあった手書き `schema.sql` は削除済み（手同期の複製は腐るため）。
参照用 DDL が必要なときは、migration 適用済みの DB から都度生成する:

```bash
mysqldump --no-data --skip-comments -h127.0.0.1 -P3585 -uroot -p nene_invoice
```
