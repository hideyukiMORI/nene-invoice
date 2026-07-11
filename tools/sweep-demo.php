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
use NeneInvoice\Demo\DemoServiceProvider;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Support\SqlLike;

require dirname(__DIR__) . '/vendor/autoload.php';

// cron / CLI 専用。Web 経由（誤配置・誤ルーティング）で叩かれる経路を閉じる（#640・他3製品と同形）。
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

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
// 溜まる。窓が完全に失効したもの（window×2 より古い）は安全に消せる（他3製品と同閾値・#640）。
$staleBefore = $clock->now()->getTimestamp() - (DemoServiceProvider::THROTTLE_WINDOW_SECONDS * 2);
foreach (glob($root . '/var/rate-limits/*.json') ?: [] as $window) {
    $mtime = @filemtime($window);
    if ($mtime !== false && $mtime < $staleBefore) {
        @unlink($window);
    }
}

// ESCAPE 明示＋prefix のワイルドカードエスケープ（clear #277 還流・#636）。
// エスケープ文字は invoice 業務クエリと同じ SqlLike の '!'（#396）。
$rows = $query->fetchAll(
    "SELECT id, created_at FROM organizations WHERE slug LIKE ? ESCAPE '!'",
    [SqlLike::escape($demo->slugPrefix) . '%'],
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
