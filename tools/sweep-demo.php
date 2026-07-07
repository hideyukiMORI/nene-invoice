<?php

/**
 * 使い捨てデモ org の掃除スクリプト（税理士リード向けデモ／`DEMO_MODE`）。
 * 使い方: php tools/sweep-demo.php   （cron 例: 毎時 0 分）
 *
 * - slug prefix `demo-` の org を対象に、作成から DEMO_TTL_HOURS（既定 3）を過ぎたものを削除。
 * - さらに容量上限 DEMO_MAX_ORGS（既定 200）を超える分は、古いものから削除（暴走・DoS 保険）。
 * - org 本体の削除は監査つきの DeleteOrganizationUseCase 経由。子テーブルは使い捨てデータなので
 *   best-effort で organization_id / 親経由に一括 DELETE（"消えていい" のが強み＝掃除は雑でよい）。
 *
 * 本番 org（`demo-` 以外の slug）には一切触れない。
 */

declare(strict_types=1);

use Nene2\Config\AppConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Organization\DeleteOrganizationUseCaseInterface;
use NeneInvoice\Organization\OrganizationNotFoundException;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$container = (new RuntimeContainerFactory($root))->create();
$container->get(AppConfig::class);

$connectionFactory = $container->get(DatabaseConnectionFactoryInterface::class);
assert($connectionFactory instanceof DatabaseConnectionFactoryInterface);
$pdo = $connectionFactory->create();

$ttlEnv   = getenv('DEMO_TTL_HOURS');
$maxEnv   = getenv('DEMO_MAX_ORGS');
$ttlHours = is_string($ttlEnv) && $ttlEnv !== '' ? (int) $ttlEnv : 3;
$maxOrgs  = is_string($maxEnv) && $maxEnv !== '' ? (int) $maxEnv : 200;
$cutoff   = date('Y-m-d H:i:s', time() - ($ttlHours * 3600));

// 1) TTL 超過。SELECT は fetchAll で完全に読み切る（カーソルを開いたまま削除しないため）。
$expiredStmt = $pdo->prepare("SELECT id FROM organizations WHERE slug LIKE 'demo-%' AND created_at < ? ORDER BY id ASC");
$expiredStmt->execute([$cutoff]);
$expired = array_map('intval', $expiredStmt->fetchAll(PDO::FETCH_COLUMN));

// 2) 容量上限。新しい方から $maxOrgs 件を残し、あふれた古い分を対象に加える。
$allDemo  = array_map('intval', $pdo->query("SELECT id FROM organizations WHERE slug LIKE 'demo-%' ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_COLUMN));
$overflow = array_slice($allDemo, $maxOrgs);

$targets = array_values(array_unique(array_merge($expired, $overflow)));

if ($targets === []) {
    echo "掃除対象なし（demo org・TTL {$ttlHours}h・上限 {$maxOrgs}）。" . PHP_EOL;

    return;
}

$delete = $container->get(DeleteOrganizationUseCaseInterface::class);
assert($delete instanceof DeleteOrganizationUseCaseInterface);

// organization_id を直接持つ子テーブル（line_items は親経由なので別処理）。
$childTables = [
    'clients', 'items', 'quotes', 'invoices', 'payments', 'bank_transactions',
    'payer_aliases', 'recurring_invoices', 'company_settings', 'document_sequences',
    'users', 'refresh_tokens', 'login_attempts', 'service_tokens',
    'payment_links', 'invoice_download_tokens', 'templates', 'company_seal_images',
];

$swept = 0;
foreach ($targets as $orgId) {
    // line_items は organization_id を持たない（parent 経由）。親削除前に片付ける。
    foreach (['invoice', 'quote', 'recurring_invoice'] as $parentType) {
        $pdo->prepare(
            "DELETE FROM line_items WHERE parent_type = ? AND parent_id IN
                (SELECT id FROM {$parentType}s WHERE organization_id = ?)",
        )->execute([$parentType, $orgId]);
    }
    foreach ($childTables as $table) {
        try {
            $pdo->prepare("DELETE FROM {$table} WHERE organization_id = ?")->execute([$orgId]);
        } catch (\PDOException) {
            // テーブルが無い環境（軽量構成）でも掃除は続行する。
        }
    }

    try {
        $delete->execute(null, $orgId);
        $swept++;
        echo "掃除: org {$orgId}" . PHP_EOL;
    } catch (OrganizationNotFoundException) {
        // すでに消えている（並行実行など）— 無視。
    }
}

echo "{$swept} 件の demo org を掃除しました（TTL {$ttlHours}h・上限 {$maxOrgs}）。" . PHP_EOL;
