<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Demo;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 実物の `tools/sweep-demo.php` をサブプロセスで実行し、ホストの既定タイムゾーンを
 * 本番（HETEML）と同じ Asia/Tokyo に固定しても TTL 判定がずれないことをピン留めする
 * 回帰テスト（#640・監査 #631-(e)。clear #280/#281・deal #72 で 2 度踏んだ型）。
 *
 * invoice は `organizations.created_at` を UtcClock で UTC 書き込みし、防御は二層:
 * ① `src/bootstrap.php` が process TZ を UTC に固定（ADR 0010）② sweep 側の
 * UTC 明示パース（`tools/sweep-demo.php`）。両方を失う退行が起きると、JST ホスト
 * では全 org が 9 時間古く読まれ、作りたての demo org が毎時 cron で即死する —
 * 本テストは `php -d date.timezone=Asia/Tokyo` の実サブプロセスで「fresh org は
 * 生存・期限切れ org は reap・demo prefix 以外の org は不可侵・再実行は冪等」を
 * JST / UTC の両ホスト構成で end-to-end にピン留めする。
 *
 * スキーマは手書きせず、phinx の実マイグレーションを temp SQLite に適用する
 * （DemoOrgReaper がガードなしで触る line_items/quotes/invoices/recurring_invoices
 * を含む全テーブルが実物と同形になる）。
 *
 * 注意: sweep は repo 実体の `var/recurring-runs/` / `var/rate-limits/` の残骸掃除も
 * 行う（テスト DB に存在しない org のスタンプはローカルでも消される）。どちらも
 * gitignore 済みの使い捨て状態で、CI では常に空。
 */
final class SweepDemoScriptTest extends TestCase
{
    private string $dbPath;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('invoice-sweep-script-', true) . '.sqlite';

        [$exitCode, , $stderr] = $this->runTool([self::root() . '/vendor/bin/phinx', 'migrate', '-c', 'phinx.php']);
        self::assertSame(0, $exitCode, 'phinx migrate failed: ' . $stderr);

        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    public function testFreshOrgSurvivesAndExpiredOrgIsReapedOnAJstHost(): void
    {
        $nowUtc = time();
        $this->insertOrg(1, 'demo-fresh', gmdate('Y-m-d H:i:s', $nowUtc - 60));            // 作成 1 分後
        $this->insertOrg(2, 'demo-expired', gmdate('Y-m-d H:i:s', $nowUtc - 4 * 3600));    // TTL 3h 超過
        $this->insertOrg(3, 'honban', gmdate('Y-m-d H:i:s', $nowUtc - 30 * 24 * 3600));    // 本番 org（prefix 外）

        // reap 対象 org の子データ（ガードなしの line_items 経路を含む）。
        $this->pdo->exec("INSERT INTO users (organization_id, email, password_hash, role, created_at, updated_at)
            VALUES (2, 'demo@example.com', 'x', 'admin', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
        $this->pdo->exec("INSERT INTO invoices (id, organization_id, client_id, status, subtotal_cents, tax_cents, total_cents, created_at, updated_at)
            VALUES (9, 2, 1, 'draft', 0, 0, 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
        $this->pdo->exec("INSERT INTO line_items (parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, created_at, updated_at)
            VALUES ('invoice', 9, 'demo', 1, 0, 1000, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");

        $output = $this->runSweep('Asia/Tokyo');

        self::assertStringContainsString('掃除: org 2', $output);
        self::assertStringContainsString('1 件の demo org を掃除しました', $output);
        self::assertSame(['demo-fresh', 'honban'], $this->remainingSlugs());
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM users WHERE organization_id = 2'));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM invoices WHERE organization_id = 2'));
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM line_items WHERE parent_type = 'invoice' AND parent_id = 9"));
    }

    public function testBehavesIdenticallyOnAUtcHostAndIsIdempotent(): void
    {
        $nowUtc = time();
        $this->insertOrg(1, 'demo-fresh', gmdate('Y-m-d H:i:s', $nowUtc - 60));
        $this->insertOrg(2, 'demo-expired', gmdate('Y-m-d H:i:s', $nowUtc - 4 * 3600));

        $output = $this->runSweep('UTC');
        self::assertStringContainsString('1 件の demo org を掃除しました', $output);
        self::assertSame(['demo-fresh'], $this->remainingSlugs());

        // 再実行: reap 済み org は消えており、掃除対象なしの no-op で終わる（冪等）。
        $again = $this->runSweep('UTC');
        self::assertStringContainsString('0 件の demo org を掃除しました', $again);
        self::assertSame(['demo-fresh'], $this->remainingSlugs());
    }

    private function insertOrg(int $id, string $slug, string $createdAtUtc): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO organizations (id, name, slug, plan, is_active, created_at, updated_at)
             VALUES (?, 'Demo', ?, 'free', 1, ?, ?)",
        );
        $stmt->execute([$id, $slug, $createdAtUtc, $createdAtUtc]);
    }

    private function countRows(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<string> 残存 org slug（昇順） */
    private function remainingSlugs(): array
    {
        $stmt = $this->pdo->query('SELECT slug FROM organizations ORDER BY slug');
        self::assertNotFalse($stmt);

        /** @var list<string> */
        return array_map(strval(...), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function runSweep(string $timezone): string
    {
        [$exitCode, $stdout, $stderr] = $this->runTool([
            PHP_BINARY,
            '-d', 'date.timezone=' . $timezone,
            self::root() . '/tools/sweep-demo.php',
        ]);

        self::assertSame(0, $exitCode, 'sweep-demo.php failed: ' . $stderr);

        return $stdout;
    }

    /**
     * @param list<string> $command
     * @return array{int, string, string} exit code / stdout / stderr
     */
    private function runTool(array $command): array
    {
        // 明示 env が repo の .env に勝つ（NENE2 EnvFileLoader は実環境変数を優先）。
        $env = [
            'APP_ENV' => 'test',
            'DB_ADAPTER' => 'sqlite',
            'DB_NAME' => $this->dbPath,
            'NENE2_ALLOW_DEV_SECRET' => '1',
            'DEMO_SLUG_PREFIX' => 'demo-',
            'DEMO_TTL_HOURS' => '3',
            'DEMO_MAX_ORGS' => '200',
            'PATH' => (string) getenv('PATH'),
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::root(), $env);
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
