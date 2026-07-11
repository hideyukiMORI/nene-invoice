<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * organizations.custom_domain に unique index を追加する（#646・監査 #631-(h)）。
 *
 * findByCustomDomain() はテナント解決経路（CustomDomainResolutionStrategy）で
 * 使われるため、重複登録を DB 制約で防ぎ、解決クエリの full scan も解消する。
 * NULL 許容カラムのため、MySQL / SQLite とも NULL 複数行（custom_domain 未設定の
 * org 多数）はそのまま許容される。vault の同スキーマ・同名規約に統一。
 */
final class AddUniqueIndexToOrganizationsCustomDomain extends AbstractMigration
{
    public function change(): void
    {
        $this->table('organizations')
            ->addIndex(['custom_domain'], ['unique' => true, 'name' => 'uniq_organizations_custom_domain'])
            ->update();
    }
}
