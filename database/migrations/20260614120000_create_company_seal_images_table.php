<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Company seal image (社印) storage (Issue #448). One PNG per organization,
 * stored base64-encoded and tenant-scoped by organization_id. Kept in its own
 * table so the (relatively large) image bytes are never loaded by the normal
 * company-settings fetch — only when rendering a PDF or the settings preview.
 *
 * The seal is a sensitive, legally meaningful asset: it is served only through
 * authenticated, organization-scoped endpoints, never a public URL.
 */
final class CreateCompanySealImagesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('company_seal_images')
            ->addColumn('organization_id', 'integer', ['null' => false])
            ->addColumn('image_base64', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['unique' => true, 'name' => 'uniq_company_seal_images_organization_id'])
            ->create();
    }
}
