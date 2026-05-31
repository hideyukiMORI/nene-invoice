<?php

/**
 * 開発用ダミーデータシードスクリプト
 * 使い方: php tools/seed-dev.php
 *
 * 既存レコードと衝突しないよう INSERT OR IGNORE を使用。
 * 実行のたびに冪等に動作する（二重投入にならない）。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$dbName = getenv('DB_NAME') ?: 'var/nene_invoice.sqlite';
if (!str_starts_with($dbName, '/')) {
    $dbName = dirname(__DIR__) . '/' . $dbName;
}

$pdo = new PDO('sqlite:' . $dbName, options: [
    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$now = date('Y-m-d H:i:s');
$orgId = 1;

// ----------------------------------------------------------------
// 0. 既存データ取得
// ----------------------------------------------------------------
$existingClients   = $pdo->query("SELECT id FROM clients WHERE is_deleted=0 AND organization_id={$orgId}")->fetchAll(PDO::FETCH_COLUMN);
$existingQuotes    = $pdo->query("SELECT id FROM quotes WHERE is_deleted=0 AND organization_id={$orgId}")->fetchAll(PDO::FETCH_COLUMN);
$existingInvoices  = $pdo->query("SELECT id FROM invoices WHERE is_deleted=0 AND organization_id={$orgId}")->fetchAll(PDO::FETCH_COLUMN);

echo "現在: clients=" . count($existingClients) . " quotes=" . count($existingQuotes) . " invoices=" . count($existingInvoices) . PHP_EOL;

// ----------------------------------------------------------------
// 1. 会社設定を充実させる
// ----------------------------------------------------------------
$pdo->exec("UPDATE company_settings SET
    legal_name          = '株式会社ネネ商会',
    address             = '東京都渋谷区道玄坂1-1-1 ネネビル3F',
    phone               = '03-1234-5678',
    email               = 'info@nene-shokai.example',
    registration_number = 'T1234567890123',
    bank_name           = 'みずほ銀行',
    bank_branch         = '渋谷',
    account_type        = '普通',
    account_number      = '1234567',
    updated_at          = '{$now}'
    WHERE organization_id = {$orgId}");
echo "✓ 会社設定更新" . PHP_EOL;

// ----------------------------------------------------------------
// 2. ユーザー追加
// ----------------------------------------------------------------
$users = [
    ['member@example.com', 'member'],
    ['viewer@example.com', 'viewer'],
];
$stmt = $pdo->prepare("INSERT OR IGNORE INTO users (organization_id, email, password_hash, role, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'active', ?, ?)");
foreach ($users as [$email, $role]) {
    $hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt->execute([$orgId, $email, $hash, $role, $now, $now]);
    echo "✓ ユーザー: {$email} ({$role})" . PHP_EOL;
}

// ----------------------------------------------------------------
// 3. 取引先
// ----------------------------------------------------------------
$clients = [
    ['株式会社サンプル製作所',   '田中 一郎', 'tanaka@sample.example',    '東京都新宿区西新宿2-1-1',    'T9876543210123'],
    ['合同会社テストエージェンシー', '佐藤 花子', 'sato@test-agency.example', '大阪府大阪市北区梅田1-1-3', null],
    ['有限会社フリーワークス',    '鈴木 次郎', 'suzuki@freeworks.example', '愛知県名古屋市中村区1-2-3',  'T1122334455667'],
    ['個人事業主 山田 太郎',     null,        'yamada@personal.example',  null,                         null],
    ['大手商社 グローバル株式会社', '伊藤 三郎', 'ito@global.example',       '東京都千代田区大手町1-1-1',  'T5566778899001'],
    ['スタートアップ合同会社',    '渡辺 四郎', null,                        '福岡県福岡市博多区博多駅前',  null],
];

$clientIds = [];
// 既存クライアント1件を含める
if (!empty($existingClients)) {
    $clientIds[] = (int) $existingClients[0];
}

$stmt = $pdo->prepare("INSERT INTO clients (organization_id, name, contact_name, email, billing_address, registration_number, is_deleted, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
foreach ($clients as [$name, $contact, $email, $address, $regnum]) {
    $stmt->execute([$orgId, $name, $contact, $email, $address, $regnum, $now, $now]);
    $clientIds[] = (int) $pdo->lastInsertId();
    echo "✓ 取引先: {$name}" . PHP_EOL;
}

// ----------------------------------------------------------------
// 4. 見積書（6件・各ステータス）
// ----------------------------------------------------------------
/** @param list<array{string,int,int,int}> $lines description, qty, unit_cents, tax_bps */
function calcTotals(array $lines): array
{
    $subtotal = 0;
    $taxByRate = [];
    foreach ($lines as [$desc, $qty, $unit, $taxBps]) {
        $lineSubtotal = $qty * $unit;
        $subtotal += $lineSubtotal;
        $taxByRate[$taxBps] = ($taxByRate[$taxBps] ?? 0) + $lineSubtotal;
    }
    $taxCents = 0;
    foreach ($taxByRate as $bps => $taxable) {
        $taxCents += (int) round($taxable * $bps / 10000);
    }
    return [$subtotal, $taxCents, $subtotal + $taxCents];
}

$quoteRows = [
    // [client_idx, status, quote_number, issued_at, valid_until, notes, lines]
    [
        0, 'draft', 'EST-2026-101', null, '2026-06-30', 'ご検討のほどよろしくお願いします。',
        [['Webサイト制作（基本プラン）', 1, 300000, 1000], ['保守費用（月額）', 3, 50000, 1000]],
    ],
    [
        1, 'sent', 'EST-2026-102', '2026-05-10', '2026-06-10', null,
        [['システム開発 フェーズ1', 1, 800000, 1000], ['要件定義', 1, 150000, 1000], ['テスト費用', 1, 100000, 1000]],
    ],
    [
        2, 'accepted', 'EST-2026-103', '2026-05-01', '2026-05-31', 'ありがとうございます。',
        [['コンサルティング（10時間）', 10, 30000, 1000], ['資料作成', 1, 50000, 1000]],
    ],
    [
        3, 'rejected', 'EST-2026-104', '2026-04-15', '2026-05-15', null,
        [['パンフレット制作', 1, 200000, 1000]],
    ],
    [
        4, 'expired', 'EST-2026-105', '2026-03-01', '2026-03-31', null,
        [['広告運用代行（1ヶ月）', 1, 120000, 1000], ['クリエイティブ制作', 1, 80000, 1000]],
    ],
    [
        5, 'draft', 'EST-2026-106', null, '2026-07-31', '軽減税率適用品目あり。',
        [['食品EC サイト構築', 1, 500000, 1000], ['食材仕入れコンサル', 1, 100000, 800]],
    ],
];

$quoteIds = [];
$qStmt = $pdo->prepare("INSERT INTO quotes (organization_id, client_id, quote_number, status, subtotal_cents, tax_cents, total_cents, issued_at, valid_until, notes, is_deleted, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
$lStmt = $pdo->prepare("INSERT INTO line_items (parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at)
    VALUES ('quote', ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($quoteRows as $i => [$cIdx, $status, $number, $issuedAt, $validUntil, $notes, $lines]) {
    [$sub, $tax, $total] = calcTotals($lines);
    $qStmt->execute([$orgId, $clientIds[$cIdx], $number, $status, $sub, $tax, $total, $issuedAt, $validUntil, $notes, $now, $now]);
    $qId = (int) $pdo->lastInsertId();
    $quoteIds[] = $qId;
    foreach ($lines as $j => [$desc, $qty, $unit, $taxBps]) {
        $lStmt->execute([$qId, $desc, $qty, $unit, $taxBps, $j, $now, $now]);
    }
    echo "✓ 見積書: {$number} ({$status})" . PHP_EOL;
}

// document_sequences for quotes
$pdo->exec("INSERT INTO document_sequences (organization_id, doc_type, year, last_number) VALUES ({$orgId}, 'quote', 2026, 106)
    ON CONFLICT(organization_id, doc_type, year) DO UPDATE SET last_number=MAX(last_number, 106)");

// ----------------------------------------------------------------
// 5. 請求書（8件・各ステータス）
// ----------------------------------------------------------------
$invoiceRows = [
    // [client_idx, status, invoice_number, issued_at, due_at, is_qualified, notes, lines, payments]
    [
        // 発行済み・期限超過
        0, 'issued', 'INV-2026-101', '2026-04-01', '2026-04-30', true, '4月分ご請求書',
        [['Webサイト制作（基本プラン）', 1, 300000, 1000], ['保守費用', 3, 50000, 1000]],
        [],
    ],
    [
        // 発行済み・通常（支払期限今月）
        1, 'issued', 'INV-2026-102', '2026-05-15', '2026-06-15', true, null,
        [['システム開発 フェーズ1', 1, 800000, 1000], ['テスト費用', 1, 100000, 1000]],
        [],
    ],
    [
        // 一部入金
        2, 'partially_paid', 'INV-2026-103', '2026-05-01', '2026-05-31', true, 'コンサルフェーズ1',
        [['コンサルティング（10時間）', 10, 30000, 1000], ['資料作成', 1, 50000, 1000]],
        [['2026-05-20', 200000, 'bank_transfer', '5月分一部入金']],
    ],
    [
        // 入金済み
        4, 'paid', 'INV-2026-104', '2026-04-15', '2026-05-15', false, null,
        [['広告運用代行（1ヶ月）', 1, 120000, 1000], ['クリエイティブ制作', 1, 80000, 1000]],
        [['2026-05-01', 110000, 'bank_transfer', '第1回'], ['2026-05-10', 110000, 'bank_transfer', '第2回']],
    ],
    [
        // 下書き（番号なし）
        3, 'draft', null, null, null, false, '見積書変換後の下書き',
        [['パンフレット制作', 1, 200000, 1000]],
        [],
    ],
    [
        // 適格請求書・発行済み
        0, 'issued', 'INV-2026-106', '2026-05-20', '2026-06-20', true, '適格請求書（軽減税率混在）',
        [['一般商品', 2, 50000, 1000], ['食料品（軽減）', 5, 20000, 800]],
        [],
    ],
    [
        // 発行済み・期限超過（超過日数大）
        5, 'issued', 'INV-2026-107', '2026-03-01', '2026-03-31', true, '3月分・長期未収',
        [['月額サポート', 1, 150000, 1000]],
        [],
    ],
    [
        // 一部入金・期限超過
        2, 'partially_paid', 'INV-2026-108', '2026-04-01', '2026-04-30', true, '4月コンサル・残高あり',
        [['コンサルティング（5時間）', 5, 30000, 1000], ['報告書作成', 1, 50000, 1000]],
        [['2026-04-25', 100000, 'cash', '現金先払い分']],
    ],
];

$iStmt = $pdo->prepare("INSERT INTO invoices (organization_id, client_id, status, invoice_number, subtotal_cents, tax_cents, total_cents, is_qualified_invoice, quote_id, issued_at, due_at, notes, is_deleted, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, null, ?, ?, ?, 0, ?, ?)");
$pStmt = $pdo->prepare("INSERT INTO payments (organization_id, invoice_id, amount_cents, paid_at, method, note, is_deleted, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");

foreach ($invoiceRows as [$cIdx, $status, $number, $issuedAt, $dueAt, $isQualified, $notes, $lines, $payments]) {
    [$sub, $tax, $total] = calcTotals($lines);
    $iStmt->execute([$orgId, $clientIds[$cIdx], $status, $number, $sub, $tax, $total, $isQualified ? 1 : 0, $issuedAt, $dueAt, $notes, $now, $now]);
    $iId = (int) $pdo->lastInsertId();
    // line_items には organization_id カラムなし（テナントは parent 経由）
    $liStmt = $pdo->prepare("INSERT INTO line_items (parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at)
        VALUES ('invoice', ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($lines as $j => [$desc, $qty, $unit, $taxBps]) {
        $liStmt->execute([$iId, $desc, $qty, $unit, $taxBps, $j, $now, $now]);
    }
    foreach ($payments as [$paidAt, $amount, $method, $note]) {
        $pStmt->execute([$orgId, $iId, $amount, $paidAt, $method, $note, $now, $now]);
    }
    $displayNum = $number ?? '（下書き）';
    echo "✓ 請求書: {$displayNum} ({$status})" . PHP_EOL;
}

// document_sequences for invoices
$pdo->exec("INSERT INTO document_sequences (organization_id, doc_type, year, last_number) VALUES ({$orgId}, 'invoice', 2026, 108)
    ON CONFLICT(organization_id, doc_type, year) DO UPDATE SET last_number=MAX(last_number, 108)");

// ----------------------------------------------------------------
// 6. 完了サマリ
// ----------------------------------------------------------------
echo PHP_EOL . "=== 完了 ===" . PHP_EOL;
echo "取引先: " . $pdo->query("SELECT COUNT(*) FROM clients WHERE organization_id={$orgId} AND is_deleted=0")->fetchColumn() . "件" . PHP_EOL;
echo "見積書: " . $pdo->query("SELECT COUNT(*) FROM quotes WHERE organization_id={$orgId} AND is_deleted=0")->fetchColumn() . "件" . PHP_EOL;
echo "請求書: " . $pdo->query("SELECT COUNT(*) FROM invoices WHERE organization_id={$orgId} AND is_deleted=0")->fetchColumn() . "件" . PHP_EOL;
echo "入金  : " . $pdo->query("SELECT COUNT(*) FROM payments WHERE organization_id={$orgId} AND is_deleted=0")->fetchColumn() . "件" . PHP_EOL;
echo "ユーザー: " . $pdo->query("SELECT COUNT(*) FROM users WHERE organization_id={$orgId}")->fetchColumn() . "名" . PHP_EOL;
