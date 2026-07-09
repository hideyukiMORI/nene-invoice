<?php

/**
 * 使い捨てデモ org の掃除スクリプト（税理士リード向けデモ／`DEMO_MODE`）。
 * 使い方: php tools/sweep-demo.php   （cron 例: 毎時 0 分）
 *
 * TTL（DEMO_TTL_HOURS・既定 3）と容量上限（DEMO_MAX_ORGS・既定 200）の判定は
 * NENE2 の `Nene2\Demo\DisposableDemoSweeper` に委譲する（#610 consumer 化）。
 * 破棄（子テーブル・org 本体・recurring 実行スタンプ）は invoice の
 * {@see \NeneInvoice\Demo\DemoOrgReaper}。このスクリプトは demo org の一覧を
 * SELECT して渡すだけ — `slug LIKE '{prefix}%'` の絞り込みが本番 org を守る唯一の
 * 境界なので、絶対に広げないこと。
 *
 * 本番 org（demo prefix 以外の slug）には一切触れない。
 */

declare(strict_types=1);

use Nene2\Config\AppConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DemoOrgRecord;
use Nene2\Demo\DisposableDemoSweeper;
use Nene2\Demo\DisposableOrgReaperInterface;
use Nene2\Http\ClockInterface;
use NeneInvoice\Http\RuntimeContainerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$container = (new RuntimeContainerFactory($root))->create();

$config = $container->get(AppConfig::class);
assert($config instanceof AppConfig);
$demo = $config->demo;

$query = $container->get(DatabaseQueryExecutorInterface::class);
assert($query instanceof DatabaseQueryExecutorInterface);

$clock = $container->get(ClockInterface::class);
assert($clock instanceof ClockInterface);

// RECURRING_INLINE の実行スタンプ（FileRecurringRunThrottle・var/recurring-runs/org-{id}.txt）は
// ファイルなので DB 掃除では消えず、demo org の回転数だけ無限蓄積する。organizations に
// 存在しない org の残骸だけ自己修復で消す（実在 org — 本番含む — のスタンプには触れない）。
// per-org のスタンプ削除は DemoOrgReaper がやる。ここは過去の取りこぼし回収なので
// 掃除対象ゼロの回でも走らせる。
foreach (glob($root . '/var/recurring-runs/org-*.txt') ?: [] as $marker) {
    if (preg_match('/org-(\d+)\.txt$/', $marker, $m) !== 1) {
        continue;
    }
    if ($query->fetchOne('SELECT 1 AS x FROM organizations WHERE id = ?', [(int) $m[1]]) === null) {
        @unlink($marker);
        echo '残骸スタンプ掃除: ' . basename($marker) . PHP_EOL;
    }
}

// デモ throttle の窓ファイル（FileRateLimitStorage・var/rate-limits/*.json）も IP の数だけ
// 溜まる。窓はどれだけ長くても数時間なので、丸2日触られていないファイルは安全に消せる。
$staleBefore = $clock->now()->getTimestamp() - (2 * 86400);
foreach (glob($root . '/var/rate-limits/*.json') ?: [] as $window) {
    $mtime = @filemtime($window);
    if ($mtime !== false && $mtime < $staleBefore) {
        @unlink($window);
    }
}

$rows = $query->fetchAll(
    'SELECT id, created_at FROM organizations WHERE slug LIKE ?',
    [$demo->slugPrefix . '%'],
);

$records = array_map(
    static fn (array $row): DemoOrgRecord => new DemoOrgRecord(
        (int) $row['id'],
        new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
    ),
    $rows,
);

if ($records === []) {
    echo "掃除対象なし（demo org・TTL {$demo->ttlHours}h・上限 {$demo->maxOrgs}）。" . PHP_EOL;

    return;
}

$reaper = $container->get(DisposableOrgReaperInterface::class);
assert($reaper instanceof DisposableOrgReaperInterface);

$report = (new DisposableDemoSweeper($demo, $reaper, $clock))->sweep($records);

foreach ($report->reapedOrgIds as $orgId) {
    echo "掃除: org {$orgId}" . PHP_EOL;
}

echo count($report->reapedOrgIds) . " 件の demo org を掃除しました（TTL {$demo->ttlHours}h・上限 {$demo->maxOrgs}）。" . PHP_EOL;
