<?php

declare(strict_types=1);

namespace NeneInvoice\Demo;

use DateTimeImmutable;
use InvalidArgumentException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DemoDataSeederInterface;
use Nene2\Demo\DemoTemplateKeyInterface;
use Nene2\Http\ClockInterface;

/**
 * Seeds a freshly created (disposable) demo organization with realistic,
 * industry-specific business data for the tax-accountant demo (2026-07-07).
 *
 * Writes go through the shared {@see DatabaseQueryExecutorInterface} (one
 * connection per request) — deliberately NOT a second PDO, which under SQLite
 * would contend with the app's own connection ("database is locked"). Every row
 * carries an explicit `organization_id` (never a request-scoped holder): the demo
 * route is org-less at entry and provisions a brand-new tenant, so seeding is a
 * deliberate cross-tenant write into the org the caller just created — the same
 * shape as {@see \NeneInvoice\Organization\PdoInitialAdminRepository}.
 *
 * All dates are relative to today (T) so the data always looks "current": a few
 * invoices issued this month, one or two overdue, some paid this month. Dates of
 * *historical* events (issue dates, payments, bank transactions) are clamped to
 * today at the inserters — day-of-month anchors like "the 15th" must never
 * produce a future receipt when the demo is opened early in the month. Due
 * dates are deliberately NOT clamped (a future due date is normal). Amounts,
 * withholding, and registration numbers follow the seed specification
 * (`handoff-invoice-demo-seed-2026-07-07.md`). Each template carries exactly one
 * payer-name-mismatch reconciliation case (`payer_aliases` + `bank_transactions`).
 */
final class DemoDataSeeder implements DemoDataSeederInterface
{
    private int $orgId;
    private string $now;
    private DateTimeImmutable $today;

    public function __construct(
        private readonly DatabaseQueryExecutorInterface $query,
        private readonly ClockInterface $clock,
    ) {
    }

    public function seed(int $orgId, DemoTemplateKeyInterface $template): void
    {
        if (!$template instanceof DemoTemplate) {
            throw new InvalidArgumentException('Seeder received a template key that is not a NeneInvoice DemoTemplate.');
        }

        $this->orgId = $orgId;
        $nowDt = $this->clock->now();
        $this->now = $nowDt->format('Y-m-d H:i:s');
        // Midnight of the clock's current date (an explicit date string, not a
        // wall-clock read), so relative seed dates anchor to a stable "today".
        $this->today = new DateTimeImmutable($nowDt->format('Y-m-d'));

        match ($template) {
            DemoTemplate::Kensetsu => $this->seedKensetsu(),
            DemoTemplate::Bldmainte => $this->seedBldmainte(),
            DemoTemplate::Seisaku => $this->seedSeisaku(),
        };
    }

    // ---------------------------------------------------------------- dates
    private function dayThisMonth(int $d): string
    {
        return $this->today->format('Y-m-') . sprintf('%02d', $d);
    }

    private function dayPrevMonth(int $d): string
    {
        return $this->today->modify('first day of last month')->format('Y-m-') . sprintf('%02d', $d);
    }

    private function endThisMonth(): string
    {
        return $this->today->modify('last day of this month')->format('Y-m-d');
    }

    private function endPrevMonth(): string
    {
        return $this->today->modify('last day of last month')->format('Y-m-d');
    }

    private function endTwoMonthsAgo(): string
    {
        return $this->today->modify('-2 months')->modify('last day of this month')->format('Y-m-d');
    }

    private function dayTwoMonthsAgo(int $d): string
    {
        return $this->today->modify('-2 months')->format('Y-m-') . sprintf('%02d', $d);
    }

    private function endNextMonth(): string
    {
        return $this->today->modify('last day of next month')->format('Y-m-d');
    }

    private function firstOfNextMonth(): string
    {
        return $this->today->modify('first day of next month')->format('Y-m-d');
    }

    private function year(): int
    {
        return (int) $this->today->format('Y');
    }

    /**
     * Clamps a historical event date (issue / payment / bank transaction) to
     * today. Day-of-month anchors ("the 15th", "end of this month") land in the
     * future when the demo is opened early in the month, and a future-dated
     * receipt is exactly the kind of wart the target audience (accountants)
     * notices first. `Y-m-d` strings compare correctly as strings.
     */
    private function noLaterThanToday(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return min($date, $this->today->format('Y-m-d'));
    }

    // ------------------------------------------------------------- inserters
    private function setCompanySettings(
        string $legalName,
        string $address,
        string $phone,
        string $email,
        string $registrationNumber,
        string $bankName,
        string $bankBranch,
        string $accountType,
        string $accountNumber,
    ): void {
        $this->query->execute(
            'INSERT INTO company_settings
                (organization_id, legal_name, address, phone, email, registration_number,
                 bank_name, bank_branch, account_type, account_number, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->orgId, $legalName, $address, $phone, $email, $registrationNumber,
                $bankName, $bankBranch, $accountType, $accountNumber, $this->now, $this->now,
            ],
        );
    }

    private function insertClient(string $name, ?string $kana, ?string $contact, ?string $email, ?string $address, ?string $regnum): int
    {
        return $this->query->insert(
            'INSERT INTO clients
                (organization_id, name, name_kana, contact_name, email, billing_address, registration_number, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$this->orgId, $name, $kana, $contact, $email, $address, $regnum, $this->now, $this->now],
        );
    }

    private function insertItem(string $description, int $unitCents, int $taxBps = 1000): void
    {
        $this->query->execute(
            'INSERT INTO items
                (organization_id, description, default_unit_price_cents, default_tax_rate_bps, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$this->orgId, $description, $unitCents, $taxBps, $this->now, $this->now],
        );
    }

    /**
     * @param list<array{string,int,int,int}> $lines [description, qty, unit_cents, tax_bps]
     * @return array{0:int,1:int,2:int}                [subtotal, tax, total] in cents
     */
    private function calcTotals(array $lines): array
    {
        $subtotal = 0;
        $taxByRate = [];
        foreach ($lines as [$desc, $qty, $unit, $taxBps]) {
            $lineSubtotal = $qty * $unit;
            $subtotal += $lineSubtotal;
            $taxByRate[$taxBps] = ($taxByRate[$taxBps] ?? 0) + $lineSubtotal;
        }
        $tax = 0;
        foreach ($taxByRate as $bps => $taxable) {
            $tax += (int) round($taxable * $bps / 10000);
        }

        return [$subtotal, $tax, $subtotal + $tax];
    }

    /** @param list<array{string,int,int,int}> $lines */
    private function insertLines(string $parentType, int $parentId, array $lines): void
    {
        foreach ($lines as $i => [$desc, $qty, $unit, $taxBps]) {
            $this->query->execute(
                'INSERT INTO line_items
                    (parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$parentType, $parentId, $desc, $qty, $unit, $taxBps, $i, $this->now, $this->now],
            );
        }
    }

    /** @param list<array{string,int,int,int}> $lines */
    private function insertQuote(int $clientId, string $number, string $status, ?string $issuedAt, ?string $validUntil, ?string $notes, array $lines): int
    {
        [$sub, $tax, $total] = $this->calcTotals($lines);
        $id = $this->query->insert(
            'INSERT INTO quotes
                (organization_id, client_id, quote_number, status, issued_at, valid_until, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$this->orgId, $clientId, $number, $status, $this->noLaterThanToday($issuedAt), $validUntil, $sub, $tax, $total, $notes, $this->now, $this->now],
        );
        $this->insertLines('quote', $id, $lines);

        return $id;
    }

    /** @param list<array{string,int,int,int}> $lines */
    private function insertInvoice(
        int $clientId,
        ?string $number,
        string $status,
        bool $isQualified,
        ?string $issuedAt,
        ?string $dueAt,
        ?string $notes,
        array $lines,
        ?int $quoteId = null,
    ): int {
        [$sub, $tax, $total] = $this->calcTotals($lines);
        $id = $this->query->insert(
            'INSERT INTO invoices
                (organization_id, client_id, quote_id, invoice_number, status, is_qualified_invoice, issued_at, due_at, subtotal_cents, tax_cents, total_cents, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $this->orgId, $clientId, $quoteId, $number, $status, $isQualified ? 1 : 0,
                $this->noLaterThanToday($issuedAt), $dueAt, $sub, $tax, $total, $notes, $this->now, $this->now,
            ],
        );
        $this->insertLines('invoice', $id, $lines);

        return $id;
    }

    private function insertPayment(int $invoiceId, int $amountCents, string $paidAt, string $method, ?string $note): int
    {
        return $this->query->insert(
            'INSERT INTO payments
                (organization_id, invoice_id, amount_cents, paid_at, method, note, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$this->orgId, $invoiceId, $amountCents, (string) $this->noLaterThanToday($paidAt), $method, $note, $this->now, $this->now],
        );
    }

    private function insertPayerAlias(string $normalizedName, int $clientId): void
    {
        $this->query->execute(
            'INSERT INTO payer_aliases (organization_id, normalized_name, client_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)',
            [$this->orgId, $normalizedName, $clientId, $this->now, $this->now],
        );
    }

    private function insertBankTransaction(
        string $valueDate,
        int $amountCents,
        string $payerName,
        string $description,
        string $status,
        ?int $matchedInvoiceId = null,
        ?int $matchedPaymentId = null,
    ): void {
        $this->query->execute(
            'INSERT INTO bank_transactions
                (organization_id, value_date, direction, amount_cents, payer_name, description, bank_reference, status, matched_invoice_id, matched_payment_id, imported_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->orgId, (string) $this->noLaterThanToday($valueDate), 'credit', $amountCents, $payerName, $description, null,
                $status, $matchedInvoiceId, $matchedPaymentId, $this->now, $this->now, $this->now,
            ],
        );
    }

    /** @param list<array{string,int,int,int}> $lines */
    private function insertRecurring(int $clientId, string $name, array $lines, string $nextRunOn, ?string $notes): void
    {
        [$sub, $tax, $total] = $this->calcTotals($lines);
        $id = $this->query->insert(
            'INSERT INTO recurring_invoices
                (organization_id, client_id, name, frequency, subtotal_cents, tax_cents, total_cents, next_run_on, is_active, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 0, ?, ?)',
            [$this->orgId, $clientId, $name, 'monthly', $sub, $tax, $total, $nextRunOn, $notes, $this->now, $this->now],
        );
        $this->insertLines('recurring_invoice', $id, $lines);
    }

    private function upsertSequence(string $docType, int $year, int $lastNumber): void
    {
        $this->query->execute(
            'INSERT INTO document_sequences (organization_id, doc_type, year, last_number) VALUES (?, ?, ?, ?)',
            [$this->orgId, $docType, $year, $lastNumber],
        );
    }

    private function invoiceNumber(int $n): string
    {
        return sprintf('INV-%d-%03d', $this->year(), $n);
    }

    private function quoteNumber(int $n): string
    {
        return sprintf('EST-%d-%03d', $this->year(), $n);
    }

    // ============================================================ templates
    private function seedKensetsu(): void
    {
        $this->setCompanySettings(
            '株式会社山手建設',
            '東京都渋谷区神南1-2-3 山手ビル5F',
            '03-5555-1234',
            'info@yamate-kensetsu.example',
            'T1010001011111',
            'みずほ銀行',
            '渋谷支店',
            '普通',
            '1234567',
        );

        foreach (['木工事', '基礎工事', '仮設工事', '内装工事', '諸経費'] as $item) {
            $this->insertItem($item, 100000);
        }

        $c1 = $this->insertClient('大京建設株式会社', 'ダイキョウケンセツ', '部長 加藤', 'kato@daikyo-kensetsu.example', '東京都新宿区西新宿3-1-1', 'T2020002022222');
        $c2 = $this->insertClient('有限会社丸和不動産', 'マルワフドウサン', '丸山', 'maruyama@maruwa.example', '東京都杉並区高円寺1-1-1', 'T3030003033333');
        $c3 = $this->insertClient('株式会社ミナト工務店', 'ミナトコウムテン', '港', 'minato@minato-koumuten.example', '神奈川県横浜市中区港町1-1', 'T4040004044444');
        $c4 = $this->insertClient('田中 一郎', 'タナカ イチロウ', null, 'tanaka@example.example', '東京都世田谷区成城2-2-2', null);
        $c5 = $this->insertClient('佐藤 花子', 'サトウ ハナコ', null, 'sato@example.example', '東京都大田区田園調布3-3-3', null);
        $c6 = $this->insertClient('大和ハウジング株式会社', 'ヤマトハウジング', '大和', 'yamato@yamato-housing.example', '埼玉県さいたま市大宮区1-1-1', 'T5050005055555');

        // 見積（見積→請求の動線）
        $q1 = $this->insertQuote($c1, $this->quoteNumber(1), 'accepted', $this->dayPrevMonth(15), $this->endThisMonth(), '〇〇邸新築 木工事', [['木工事', 1, 2800000, 1000]]);
        $this->insertQuote($c4, $this->quoteNumber(2), 'sent', $this->dayThisMonth(3), $this->endNextMonth(), '増築工事一式', [['増築工事一式', 1, 1200000, 1000]]);

        // 請求（10件・ステータス散らし・期限超過1件）
        $i1 = $this->insertInvoice($c1, $this->invoiceNumber(1), 'paid', true, $this->dayPrevMonth(20), $this->endThisMonth(), '出来高1回目・基礎工事', [['基礎工事', 1, 1500000, 1000]]);
        $this->insertInvoice($c1, $this->invoiceNumber(2), 'issued', true, $this->dayThisMonth(5), $this->endNextMonth(), '出来高2回目・木工事', [['木工事', 1, 1000000, 1000], ['造作工事', 1, 300000, 1000]], $q1);
        $this->insertInvoice($c5, $this->invoiceNumber(3), 'issued', true, $this->endTwoMonthsAgo(), $this->endPrevMonth(), '内装工事', [['内装工事', 1, 680000, 1000]]);
        $i4 = $this->insertInvoice($c2, $this->invoiceNumber(4), 'paid', true, $this->dayPrevMonth(10), $this->dayThisMonth(10), '諸経費精算', [['諸経費', 1, 240000, 1000]]);
        $this->insertInvoice($c4, $this->invoiceNumber(5), 'issued', true, $this->dayThisMonth(1), $this->endNextMonth(), '増築・出来高1回目', [['木工事', 1, 600000, 1000]]);
        $this->insertInvoice($c3, null, 'draft', false, null, null, '仮設工事（下書き）', [['仮設工事', 1, 350000, 1000]]);
        $this->insertInvoice($c1, $this->invoiceNumber(6), 'issued', true, $this->dayPrevMonth(28), $this->endThisMonth(), '追加木工事', [['木工事', 1, 180000, 1000]]);
        $i8 = $this->insertInvoice($c5, $this->invoiceNumber(7), 'paid', true, $this->dayPrevMonth(15), $this->dayThisMonth(15), '基礎補修', [['基礎工事', 1, 120000, 1000]]);
        $this->insertInvoice($c6, null, 'draft', false, null, null, '内装工事（下書き）', [['内装工事', 1, 920000, 1000]]);
        $i10 = $this->insertInvoice($c1, $this->invoiceNumber(8), 'paid', true, $this->dayPrevMonth(5), $this->dayThisMonth(5), '諸経費', [['諸経費', 1, 75000, 1000]]);

        // 入金（paid 4件）
        $p1 = $this->insertPayment($i1, 1650000, $this->endThisMonth(), 'bank_transfer', null);
        $p4 = $this->insertPayment($i4, 264000, $this->dayThisMonth(8), 'bank_transfer', null);
        $this->insertPayment($i8, 132000, $this->dayThisMonth(15), 'bank_transfer', null);
        $this->insertPayment($i10, 82500, $this->dayThisMonth(5), 'bank_transfer', null);

        // 消込見せ場（名義ズレ1件）: 丸和不動産 → 振込名義「マルワフドウサン」
        $this->insertPayerAlias('マルワフドウサン', $c2);
        $this->insertBankTransaction($this->dayThisMonth(8), 264000, 'マルワフドウサン', '振込入金', 'posted', $i4, $p4);
        // 名義一致の通常消込（参考）
        $this->insertBankTransaction($this->endThisMonth(), 1650000, 'ダイキヨウケンセツ(カ', '振込入金', 'posted', $i1, $p1);

        $this->upsertSequence('invoice', $this->year(), 8);
        $this->upsertSequence('quote', $this->year(), 2);
    }

    private function seedBldmainte(): void
    {
        $this->setCompanySettings(
            '株式会社クリーンサポート東京',
            '東京都新宿区新宿4-5-6 CSビル2F',
            '03-6666-2345',
            'info@cleansupport.example',
            'T6060006066666',
            '三井住友銀行',
            '新宿支店',
            '普通',
            '7654321',
        );

        foreach (['日常清掃', '定期清掃', '設備点検', '貯水槽清掃', '消防設備点検'] as $item) {
            $this->insertItem($item, 50000);
        }

        $clients = [
            $this->insertClient('株式会社渋谷ビルディング', 'シブヤビルディング', '渋谷', 'shibuya@building.example', '東京都渋谷区道玄坂1-1-1', 'T7070007077777'),
            $this->insertClient('新宿センタービル管理組合', 'シンジュクセンタービル', '管理人', 'kanri@shinjuku-center.example', '東京都新宿区西新宿2-2-2', null),
            $this->insertClient('株式会社丸の内商事', 'マルノウチショウジ', '丸の内', 'marunouchi@shoji.example', '東京都千代田区丸の内1-1-1', 'T8080008088888'),
            $this->insertClient('池袋パークタワー管理組合', 'イケブクロパークタワー', '管理人', 'kanri@ikebukuro-park.example', '東京都豊島区東池袋3-3-3', null),
            $this->insertClient('有限会社原宿不動産', 'ハラジュクフドウサン', '原宿', 'harajuku@fudosan.example', '東京都渋谷区神宮前1-1-1', null),
            $this->insertClient('株式会社品川エステート', 'シナガワエステート', '品川', 'shinagawa@estate.example', '東京都港区港南2-2-2', null),
            $this->insertClient('目黒デンタルクリニック', 'メグロデンタルクリニック', '院長', 'meguro@dental.example', '東京都目黒区目黒1-1-1', null),
            $this->insertClient('株式会社上野リテール', 'ウエノリテール', '上野', 'ueno@retail.example', '東京都台東区上野4-4-4', null),
            $this->insertClient('中野サンモール商店会', 'ナカノサンモール', '会長', 'nakano@sunmall.example', '東京都中野区中野5-5-5', null),
            $this->insertClient('株式会社世田谷ハウジング', 'セタガヤハウジング', '世田谷', 'setagaya@housing.example', '東京都世田谷区太子堂1-1-1', null),
        ];

        // recurring_invoices（毎月定額・次回=翌月01）
        $recurring = [
            [0, '日常清掃', 80000], [1, '定期清掃＋設備点検', 60000], [2, '日常清掃', 50000], [3, '定期清掃', 40000],
            [4, '設備点検', 20000], [5, '日常清掃', 70000], [6, '定期清掃', 30000], [7, '日常清掃＋消防点検', 55000],
            [8, '定期清掃', 35000], [9, '貯水槽清掃', 45000],
        ];
        foreach ($recurring as [$idx, $name, $sub]) {
            $note = $idx === 9 ? '隔月請求（調整中）' : null;
            $this->insertRecurring($clients[$idx], $name, [[$name, 1, $sub, 1000]], $this->firstOfNextMonth(), $note);
        }

        // 当月分（発行=当月01・期限=当月末）— recurring から一括生成された体
        // status: paid=c1,c3,c5,c6,c7,c8 / issued(送付済)=c2,c4,c9 / draft=c10
        $monthly = [
            [0, 80000, 'paid'], [1, 60000, 'issued'], [2, 50000, 'paid'], [3, 40000, 'issued'],
            [4, 20000, 'paid'], [5, 70000, 'paid'], [6, 30000, 'paid'], [7, 55000, 'paid'],
            [8, 35000, 'issued'], [9, 45000, 'draft'],
        ];
        $seq = 0;
        $c2Invoice = null;
        foreach ($monthly as [$idx, $sub, $status]) {
            $name = $recurring[$idx][1];
            $isDraft = $status === 'draft';
            $number = $isDraft ? null : $this->invoiceNumber(++$seq);
            $issuedAt = $isDraft ? null : $this->dayThisMonth(1);
            $dueAt = $isDraft ? null : $this->endThisMonth();
            $invId = $this->insertInvoice($clients[$idx], $number, $status, !$isDraft, $issuedAt, $dueAt, '当月定期清掃料', [[$name, 1, $sub, 1000]]);
            if ($idx === 1) {
                $c2Invoice = $invId;
            }
            if ($status === 'paid') {
                $this->insertPayment($invId, (int) round($sub * 1.1), $this->dayThisMonth(10), 'bank_transfer', null);
            }
        }

        // 前月繰越（期限超過2件）: c5・c7 の前月分
        $this->insertInvoice($clients[4], $this->invoiceNumber(++$seq), 'issued', true, $this->dayPrevMonth(1), $this->endPrevMonth(), '前月分・設備点検（未入金）', [['設備点検', 1, 20000, 1000]]);
        $this->insertInvoice($clients[6], $this->invoiceNumber(++$seq), 'issued', true, $this->dayPrevMonth(1), $this->endPrevMonth(), '前月分・定期清掃（未入金）', [['定期清掃', 1, 30000, 1000]]);

        // 消込見せ場: 新宿センタービル管理組合 → 振込名義「シンジユクセンタ-ビル」（当月請求への入金）
        $this->insertPayerAlias('シンジユクセンタ-ビル', $clients[1]);
        $this->insertBankTransaction($this->dayThisMonth(5), 66000, 'シンジユクセンタ-ビル', '振込入金（要消込）', 'unmatched', $c2Invoice);

        $this->upsertSequence('invoice', $this->year(), $seq);
    }

    private function seedSeisaku(): void
    {
        $this->setCompanySettings(
            '株式会社アトリエノート',
            '東京都渋谷区恵比寿1-2-3 ノートビル4F',
            '03-7777-3456',
            'info@atelier-note.example',
            'T9090009099999',
            'GMOあおぞらネット銀行',
            '法人営業部',
            '普通',
            '2468013',
        );

        foreach (['ディレクション費', '制作費', '月額運用(顧問料)', '撮影費', '追加修正費'] as $item) {
            $this->insertItem($item, 100000);
        }

        $c1 = $this->insertClient('株式会社サンライズ広告', 'サンライズコウコク', '宣伝部', 'promo@sunrise-ad.example', '東京都港区赤坂1-1-1', 'T1112223334445');
        $c2 = $this->insertClient('合同会社ブルームテック', 'ブルームテック', 'CTO', 'cto@bloomtech.example', '東京都渋谷区桜丘町2-2-2', null);
        $c3 = $this->insertClient('株式会社北斗製作所', 'ホクトセイサクショ', '総務', 'soumu@hokuto.example', '東京都大田区蒲田1-1-1', 'T2223334445556');
        $c4 = $this->insertClient('一般社団法人みらい教育協会', 'ミライキョウイクキョウカイ', '事務局', 'jimu@mirai-edu.example', '東京都文京区本郷3-3-3', null);
        $c5 = $this->insertClient('株式会社エヌ・ワークス', 'エヌワークス', '代表', 'ceo@n-works.example', '東京都中野区中野4-4-4', null);

        // 源泉徴収あり：各請求は [報酬 @10%] ＋ [源泉徴収 マイナス行 @0%]。合計＝請求額（＝振込額）。
        // 番号は draft を除いて発番。[clientId, status, issued, due, 摘要, 報酬, 源泉, paidAmount|null]
        $rows = [
            [$c1, 'issued', $this->dayThisMonth(1), $this->endNextMonth(), '月額運用(顧問料)', 100000, 10210, null],
            [$c3, 'issued', $this->dayThisMonth(1), $this->endNextMonth(), '月額運用(顧問料)', 150000, 15315, null],
            [$c5, 'paid', $this->dayPrevMonth(1), $this->endThisMonth(), '月額運用(顧問料)', 80000, 8168, 79832],
            [$c1, 'issued', $this->dayPrevMonth(20), $this->endThisMonth(), 'スポット制作(LP一式)', 500000, 51050, null],
            [$c2, 'issued', $this->dayTwoMonthsAgo(25), $this->dayPrevMonth(25), 'ディレクション＋制作', 350000, 35735, null],
            [$c4, 'paid', $this->dayPrevMonth(10), $this->dayThisMonth(10), '撮影費', 220000, 22462, 219538],
            [$c3, 'draft', null, null, '追加修正費', 60000, 6126, null],
            [$c2, 'issued', $this->dayThisMonth(1), $this->endNextMonth(), '月額運用(顧問料)', 120000, 12252, null],
            [$c5, 'paid', $this->dayPrevMonth(28), $this->endThisMonth(), 'スポット(バナー制作)', 90000, 9189, 89811],
            [$c1, 'paid', $this->dayPrevMonth(5), $this->dayThisMonth(5), '追加ディレクション', 45000, 4594, 44906],
        ];

        $inv5 = null;
        $seq = 0;
        foreach ($rows as [$clientId, $status, $issued, $due, $summary, $reward, $withholding, $paid]) {
            $isDraft = $status === 'draft';
            $number = $isDraft ? null : $this->invoiceNumber(++$seq);
            $lines = [
                [$summary, 1, $reward, 1000],
                ['源泉徴収税', 1, -$withholding, 0],
            ];
            $invId = $this->insertInvoice($clientId, $number, $status, !$isDraft, $issued, $due, '源泉徴収あり', $lines);
            if ($summary === 'ディレクション＋制作') {
                $inv5 = $invId;
            }
            if ($paid !== null) {
                $this->insertPayment($invId, $paid, $due ?? $this->dayThisMonth(10), 'bank_transfer', null);
            }
        }

        // 消込見せ場: ブルームテック → 振込名義「ブル-ムテツク(ド」（期限超過 INV-5 への遅延入金）
        $this->insertPayerAlias('ブル-ムテツク(ド', $c2);
        $this->insertBankTransaction($this->dayThisMonth(3), 349265, 'ブル-ムテツク(ド', '振込入金（遅延・要消込）', 'unmatched', $inv5);

        $this->upsertSequence('invoice', $this->year(), $seq);
    }
}
