<?php

declare(strict_types=1);

/**
 * NeNe Invoice — Web インストーラー (Tier A 共有ホスティング向け)
 *
 * 使い方:
 *   1. このファイルにブラウザでアクセス: https://your-domain.example/install.php
 *   2. 画面の指示に従い DB 接続情報・管理者アカウントを入力
 *   3. インストール完了後は install.php を削除またはリネームしてください
 *
 * セキュリティ: インストール完了後に .installed ファイルが生成されます。
 * 既存の .installed が存在する場合はこのスクリプトを拒否します。
 *
 * 画面デザインは ClaudeDesign 提供のセットアップウィザード（deep-green SaaS
 * テーマ）に準拠。React プロトタイプを自己完結 PHP + バニラ JS に移植している。
 */

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\UtcClock;
use Nene2\Install\DatabaseSchemaApplier;
use Nene2\Install\EnvironmentWriter;
use Nene2\Install\ProvisioningProbe;
use Nene2\Install\ReInstallationGuard;
use Nene2\Install\ServerRequirementChecker;
use Nene2\Install\ServerRequirements;
use Nene2\Install\TenantConfigurationValidator;
use NeneInvoice\Http\RuntimeContainerFactory;
use NeneInvoice\Install\InstallApplication;
use NeneInvoice\Install\InstallConfig;
use NeneInvoice\Install\PayloadAcquisition;
use NeneInvoice\Install\PdoInstallProvisioningRepository;
use NeneInvoice\Organization\CreateOrganizationUseCaseInterface;
use NeneInvoice\Organization\PdoOrganizationRepository;
use Phinx\Config\Config;

define('ROOT', dirname(__DIR__));
define('INSTALLED_MARKER', ROOT . '/var/.installed');
define('ENV_FILE', ROOT . '/.env');

// -------------------------------------------------------------------
// Guard: すでにインストール済みならブロック
// -------------------------------------------------------------------
if (file_exists(INSTALLED_MARKER)) {
    refuseInstall('インストール済みです。install.php を削除してください。');
}

// -------------------------------------------------------------------
// Guard (defense in depth): マーカーが失われていても、DB に既存ユーザが
// いれば「未インストール」と誤判定して再セットアップ（.env 上書き・管理者
// 作成）されるのを防ぐ。ephemeral な var/ で .installed が消えるデプロイ対策。
// -------------------------------------------------------------------
function databaseAlreadyProvisioned(): bool
{
    if (!is_file(ENV_FILE)) {
        return false;
    }

    $env = parse_ini_file(ENV_FILE) ?: [];

    if (($env['DB_ADAPTER'] ?? 'mysql') !== 'mysql' || empty($env['DB_NAME'])) {
        return false;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'],
            $env['DB_CHARSET'] ?? 'utf8mb4',
        );
        $pdo = new PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        return $count > 0;
    } catch (\Throwable) {
        // No env / unreachable DB / no schema yet → genuinely not provisioned.
        return false;
    }
}

if (databaseAlreadyProvisioned()) {
    refuseInstall('既にプロビジョニング済みのデータベースが検出されました。再インストールはできません。install.php を削除してください。');
}

// -------------------------------------------------------------------
// ヘルパー
// -------------------------------------------------------------------
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * インストールを 403 で拒否して終了する（冒頭ガードと管理者ステップ直前の
 * 再設置確認で共用。文言は呼び出し側が持つ）。
 */
function refuseInstall(string $message): never
{
    http_response_code(403);
    echo '<p style="font-family:sans-serif;color:#c00;padding:2em">' . h($message) . '</p>';
    exit;
}

/**
 * 各サーバー要件を構造化して返す（画面表示・合否判定の単一の真実）。
 *
 * 判定は toolkit の ServerRequirementChecker に委譲する（診断のみ・FS 非変更。
 * 旧実装が判定中に行っていた var/ の @mkdir は Feature A 冒頭で明示補完する）。
 * checker は要件ごとの typed verdict を返すため、拡張 5 件を 1 行に束ねる既存
 * UI へはここで集約し、日本語の label / fix は従来のまま保つ（UI 不変）。
 *
 * @return list<array{label: string, detail: string, ok: bool, fix: string}>
 */
function requirementChecks(): array
{
    $verdicts = (new ServerRequirementChecker())->check(new ServerRequirements(
        minPhpVersion: '8.4.0',
        requiredExtensions: ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json'],
        writablePaths: [ROOT . '/var', ROOT],
        requiredFiles: [ROOT . '/vendor/autoload.php'],
    ));

    // kind（＋target）単位の合否集約。checker の reason code は表示に使わず、
    // 行ごとの日本語文言は下の配列が単一の持ち主。
    $ok = static function (string $requirement, ?string $target = null) use ($verdicts): bool {
        foreach ($verdicts as $verdict) {
            if ($verdict->requirement !== $requirement) {
                continue;
            }

            if ($target !== null && $verdict->target !== $target) {
                continue;
            }

            if (!$verdict->satisfied) {
                return false;
            }
        }

        return true;
    };

    $phpOk    = $ok(ServerRequirementChecker::REQUIREMENT_PHP);
    $extOk    = $ok(ServerRequirementChecker::REQUIREMENT_EXTENSION);
    $varOk    = $ok(ServerRequirementChecker::REQUIREMENT_WRITABLE, ROOT . '/var');
    $rootOk   = $ok(ServerRequirementChecker::REQUIREMENT_WRITABLE, ROOT);
    $vendorOk = $ok(ServerRequirementChecker::REQUIREMENT_FILE, ROOT . '/vendor/autoload.php');

    return [
        [
            'label' => 'PHP 8.4 以上',
            'detail' => '現在: ' . PHP_VERSION,
            'ok' => $phpOk,
            'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。',
        ],
        [
            'label' => 'PHP 拡張モジュール',
            'detail' => 'pdo / pdo_mysql / mbstring / openssl / json',
            'ok' => $extOk,
            'fix' => '不足している拡張モジュールを有効化してください（ホスティングのサポートにご確認ください）。',
        ],
        [
            'label' => 'var/ ディレクトリへの書き込み権限',
            'detail' => $varOk ? '書き込み可' : '書き込み不可',
            'ok' => $varOk,
            'fix' => 'ファイルマネージャまたは FTP で <code>var/</code> フォルダのパーミッションを「書き込み可（755 または 775）」に変更してください。',
        ],
        [
            'label' => 'ルートディレクトリへの書き込み権限',
            'detail' => '.env ファイルを作成します',
            'ok' => $rootOk,
            'fix' => '展開先フォルダを一時的に書き込み可にしてください。インストール完了後は元の権限に戻して構いません。',
        ],
        [
            'label' => 'vendor/ ディレクトリ（依存一式）',
            'detail' => '依存ライブラリ',
            'ok' => $vendorOk,
            'fix' => 'ZIP ファイルが完全に展開されているか確認してください。',
        ],
    ];
}

// ===================================================================
// Feature B — 手動アップロードによるアプリ取得（vendor/ 不在時）
// ===================================================================

/**
 * 展開（アップロード取得）に必要な最小要件を返す。通常の requirementChecks()
 * とは別で、DB 接続や vendor/ の存在は問わない（vendor/ はこの手順で入る）。
 *
 * @return list<array{label: string, detail: string, ok: bool, fix: string}>
 */
function acquireRequirementChecks(): array
{
    $phpOk  = PHP_VERSION_ID >= 80400;
    $zipOk  = class_exists('ZipArchive');
    $varOk  = (is_dir(ROOT . '/var') || @mkdir(ROOT . '/var', 0755, true)) && is_writable(ROOT . '/var');
    $rootOk = is_writable(ROOT);

    return [
        [
            'label' => 'PHP 8.4 以上',
            'detail' => '現在: ' . PHP_VERSION,
            'ok' => $phpOk,
            'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。',
        ],
        [
            'label' => 'zip 拡張モジュール（ZipArchive）',
            'detail' => $zipOk ? '利用可' : '利用不可',
            'ok' => $zipOk,
            'fix' => 'アップロードした ZIP を展開するには <code>zip</code> 拡張が必要です。ホスティングのサポートにご確認ください。',
        ],
        [
            'label' => 'var/ ディレクトリへの書き込み権限',
            'detail' => $varOk ? '書き込み可' : '書き込み不可',
            'ok' => $varOk,
            'fix' => 'ファイルマネージャまたは FTP で <code>var/</code> フォルダのパーミッションを「書き込み可（755 または 775）」に変更してください。',
        ],
        [
            'label' => 'ルートディレクトリへの書き込み権限',
            'detail' => 'アプリ本体を展開します',
            'ok' => $rootOk,
            'fix' => '展開先フォルダ（public_html の 1 つ上）を一時的に書き込み可にしてください。展開後は元の権限に戻して構いません。',
        ],
    ];
}

/**
 * Web アップロード（multipart POST）を検証し、一時ファイル経由で展開する。
 * 一時 ZIP は成功・失敗いずれの場合も必ず削除する。
 */
function handleAcquireUpload(): void
{
    if (!isset($_FILES['payload']) || !is_array($_FILES['payload'])) {
        throw new RuntimeException('ZIP ファイルが選択されていません。');
    }

    $err = $_FILES['payload']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'アップロードされたファイルがサーバーの上限を超えています（php.ini の upload_max_filesize / post_max_size をご確認ください）。',
            UPLOAD_ERR_PARTIAL => 'アップロードが中断されました。もう一度お試しください。',
            UPLOAD_ERR_NO_FILE => 'ZIP ファイルが選択されていません。',
            default => 'ファイルのアップロードに失敗しました（コード: ' . (int) $err . '）。',
        });
    }

    $size = (int) ($_FILES['payload']['size'] ?? 0);

    if ($size <= 0) {
        throw new RuntimeException('アップロードされたファイルが空です。');
    }

    if ($size > PayloadAcquisition::MAX_UPLOAD_BYTES) {
        throw new RuntimeException('ファイルサイズが上限（' . (int) (PayloadAcquisition::MAX_UPLOAD_BYTES / 1024 / 1024) . 'MB）を超えています。');
    }

    $origName = (string) ($_FILES['payload']['name'] ?? '');

    if (strtolower((string) pathinfo($origName, PATHINFO_EXTENSION)) !== 'zip') {
        throw new RuntimeException('.zip ファイルのみアップロードできます。');
    }

    $tmpName = (string) ($_FILES['payload']['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('アップロードされたファイルを検証できませんでした。');
    }

    $dest = ROOT . '/var/payload-upload-' . bin2hex(random_bytes(8)) . '.zip';

    if (!move_uploaded_file($tmpName, $dest)) {
        throw new RuntimeException('アップロードされたファイルを保存できませんでした。var/ の書き込み権限を確認してください。');
    }

    try {
        // SHA-256 照合 → zip-slip / トップレベル検証 → 展開（すべて展開前に検証）。
        PayloadAcquisition::verifyAndExtract($dest, (string) ($_POST['expected_sha256'] ?? ''), ROOT);
    } finally {
        // 一致・不一致・例外いずれでも一時 ZIP を削除する。
        @unlink($dest);
    }
}

/** SVG アイコン（静的・信頼済みマークアップ）。 */
function ico(string $name): string
{
    return match ($name) {
        'mono' => '<svg viewBox="0 0 42 42"><text x="-2" y="31" font-family="sans-serif" font-weight="800" font-size="32" fill="currentColor" opacity="0.4">N</text><text x="11" y="31" font-family="sans-serif" font-weight="800" font-size="32" fill="currentColor">N</text></svg>',
        'check' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>',
        'x' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
        'arrow' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h11M11 5l5 5-5 5"/></svg>',
        'back' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 10H5M9 5L4 10l5 5"/></svg>',
        'shield' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 2l5 2v3.5c0 3-2 5.3-5 6.5-3-1.2-5-3.5-5-6.5V4z"/></svg>',
        'server' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="3" width="11" height="4.5" rx="1"/><rect x="2.5" y="8.5" width="11" height="4.5" rx="1"/><circle cx="5" cy="5.25" r=".6" fill="currentColor"/><circle cx="5" cy="10.75" r=".6" fill="currentColor"/></svg>',
        'oss' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1.5l2 4.5 4.8.4-3.6 3.2 1.1 4.7L8 11.8 3.7 14.3l1.1-4.7L1.2 6.4 6 6z"/></svg>',
        'help' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M7.8 7.7a2.2 2.2 0 0 1 4.3.6c0 1.5-2.1 1.9-2.1 3"/><path d="M10 14.2v.01"/></svg>',
        'eye' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.4"/></svg>',
        'eyeoff' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l14 14"/><path d="M8.2 8.3A2.4 2.4 0 0 0 10 12.4M5.5 5.7C3.4 7 2 10 2 10s3 5.5 8 5.5c1.4 0 2.6-.4 3.7-1M16 12.7c1.3-1.3 2-2.7 2-2.7s-3-5.5-8-5.5c-.5 0-1 .05-1.4.13"/></svg>',
        'warn' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3l8 14H2z"/><path d="M10 8v4M10 14.5v.01"/></svg>',
        'trash' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 5.5h13M8 5.5V4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1.5M5.5 5.5l.7 10a1.5 1.5 0 0 0 1.5 1.4h4.6a1.5 1.5 0 0 0 1.5-1.4l.7-10"/></svg>',
        'login' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/><path d="M12 6l4 4-4 4M16 10H8"/></svg>',
        'upload' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13v2.5a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5V13"/><path d="M10 3v10M6 7l4-4 4 4"/></svg>',
        'file' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2.5H6A1.5 1.5 0 0 0 4.5 4v12A1.5 1.5 0 0 0 6 17.5h8a1.5 1.5 0 0 0 1.5-1.5V7z"/><path d="M11 2.5V7h4.5"/></svg>',
        'org' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="6" height="10" rx="1"/><rect x="11" y="3" width="6" height="14" rx="1"/><path d="M5 10h2M5 13h2M13 6h2M13 9h2M13 12h2"/></svg>',
        default => '',
    };
}

// -------------------------------------------------------------------
// POST 処理 + 画面状態
// -------------------------------------------------------------------
$step        = (int) ($_GET['step'] ?? 0);   // 0=要件チェック(landing) / 1=DB / 2=管理者
$errors      = [];                            // バナー用
$fieldErrors = [];                            // フィールド単位（管理者ステップ）
$success     = false;

// Feature B: アプリ本体（vendor/）が展開されていなければ、要件チェックで
// ハードに失敗させる代わりに「アプリの取得（アップロード）」ビューを先に出す。
$payloadPresent = file_exists(ROOT . '/vendor/autoload.php');

// 既に書き込まれた .env から利用形態を読む（管理者ステップの分岐用）。
$installedMode = 'single';
if (is_file(ENV_FILE)) {
    $envForMode    = parse_ini_file(ENV_FILE) ?: [];
    $installedMode = (string) ($envForMode['TENANT_RESOLUTION'] ?? 'single');
}
$isMultiInstall = $installedMode !== 'single';

if (!$payloadPresent) {
    // ================= Feature B: アプリ取得（アップロード）フロー =================
    // vendor 不在＝Composer autoloader も無いため、取得ロジックを持つ依存ゼロの
    // クラスを直接 require する（toolkit には依存できない）。
    require_once ROOT . '/src/Install/PayloadAcquisition.php';

    $view      = 'acquire';
    $checks    = acquireRequirementChecks();
    $reqErrors = array_values(array_filter($checks, static fn (array $c): bool => !$c['ok']));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'acquire' && $reqErrors === []) {
        try {
            handleAcquireUpload();
            // 展開成功 → install.php を再読み込み（今度は payload あり → 通常フロー）。
            header('Location: install.php');
            exit;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && $reqErrors === []
        && $_POST === []
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    ) {
        // POST 本体がサーバーの post_max_size を超えると PHP は $_POST/$_FILES を
        // 空にする。ZIP が大きい場合に無言で失敗しないよう明示的に案内する。
        $errors[] = 'アップロードがサーバーの上限（post_max_size / upload_max_filesize）を超えた可能性があります。ホスティングの PHP 設定をご確認ください。';
    }
} else {
    // ================= 通常フロー（要件 → DB → 管理者） =================
    // vendor/ が存在する通常フローでのみ toolkit（Nene2\Install）を配線する。
    // Feature B（取得）は vendor 不在時に走るため toolkit に依存できない。
    require_once ROOT . '/vendor/autoload.php';

    // 旧 requirementChecks() は判定中に var/ を @mkdir していた（診断と副作用の
    // 同居）。toolkit の checker は診断のみで FS を変更しないため、marker
    // （var/.installed）書き込みが依存する var/ の作成をここで明示的に補完する。
    if (!is_dir(ROOT . '/var')) {
        @mkdir(ROOT . '/var', 0755, true);
    }

    // 再設置ガード（.installed marker ＋ DB probe）。probe は冒頭の pre-vendor
    // ガードと同じ databaseAlreadyProvisioned() を単一の真実として包む。無名
    // クラスなのは、トップレベルで vendor の interface を implements すると
    // pre-vendor 相（Feature B）で class binding に失敗し fatal になるため。
    $reinstallGuard = new ReInstallationGuard(INSTALLED_MARKER, new class () implements ProvisioningProbe {
        public function isProvisioned(): bool
        {
            return databaseAlreadyProvisioned();
        }
    });

    $checks    = requirementChecks();
    $reqErrors = array_values(array_filter($checks, static fn (array $c): bool => !$c['ok']));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reqErrors === []) {
        if ($step === 1) {
            // DB 接続テスト + スキーマ適用 + 利用形態（Feature A）
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbPort = (int) ($_POST['db_port'] ?? 3306);
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';

            // 利用形態: single（既定・単一組織）/ multi（複数組織）。
            // multi の解決方式は用語レジストリ登録値のみ（path/subdomain/custom_domain）。
            $tenantMode = ($_POST['tenant_mode'] ?? 'single') === 'multi' ? 'multi' : 'single';
            $resolution = (string) ($_POST['tenant_resolution'] ?? 'path');
            $baseDomain = trim($_POST['base_domain'] ?? '');

            // 実効モード: single は 'single'、multi は解決方式（未登録値は path に丸め）。
            // 検証は toolkit の TenantConfigurationValidator に委譲する。invoice の登録語彙
            // （single/path/subdomain/custom_domain・subdomain のみ基準ドメイン必須）を注入し、
            // reason code を既存の日本語メッセージへ写して UI・文言は不変に保つ。
            $effectiveMode = $tenantMode === 'multi'
                ? (in_array($resolution, ['path', 'subdomain', 'custom_domain'], true) ? $resolution : 'path')
                : 'single';

            $tenantResult = (new TenantConfigurationValidator(
                ['single', 'path', 'subdomain', 'custom_domain'],
                ['subdomain'],
            ))->validate($effectiveMode, $baseDomain);

            if ($tenantResult->valid && $tenantResult->configuration !== null) {
                $tenantResolution = $tenantResult->configuration->mode;
                $envBaseDomain    = $tenantResult->configuration->baseDomain;
            } else {
                $tenantResolution = $effectiveMode;
                $envBaseDomain    = '';

                foreach ($tenantResult->errors as $code) {
                    // default（unknown_mode 等）は上の path 丸めが先に走るため通常
                    // 到達しない防御枝（語彙を広げた将来の取りこぼし対策）。
                    $errors[] = match ($code) {
                        'base_domain_required' => 'サブドメイン方式では基準ドメイン（BASE_DOMAIN）を入力してください。',
                        'base_domain_invalid'  => '基準ドメイン（BASE_DOMAIN）の形式が正しくありません（英数字・ドット・ハイフンのみ）。',
                        default                => '利用形態の設定が正しくありません。',
                    };
                }
            }

            if ($dbName === '' || $dbUser === '') {
                $errors[] = 'データベース名とユーザー名は必須です。';
            }

            if ($errors === []) {
                try {
                    // 接続テスト（資格情報の検証・失敗は PDOException → 専用の
                    // 日本語バナー）。スキーマ適用そのものは phinx が別接続で行う。
                    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                    // スキーマは phinx migration が唯一の正（決定A）。toolkit の
                    // DatabaseSchemaApplier が Manager API で pending migration を
                    // CLI なしに適用する — fresh install と upgrade が同一経路に
                    // 収束し、dump（database/schema/*.sql・参照資料へ降格）との
                    // 乖離が構造的に起きない。接続情報は検証済み POST 値から直接
                    // 渡す（.env 非依存。「schema 適用 → .env 書込」の順序と
                    // 失敗時挙動は従来どおり）。
                    (new DatabaseSchemaApplier())->apply(new Config([
                        'paths' => ['migrations' => ROOT . '/database/migrations'],
                        'environments' => [
                            'default_environment' => 'install',
                            'install' => [
                                'adapter' => 'mysql',
                                'host' => $dbHost,
                                'port' => $dbPort,
                                'name' => $dbName,
                                'user' => $dbUser,
                                'pass' => $dbPass,
                                'charset' => 'utf8mb4',
                            ],
                        ],
                        // phinx.php と揃えること（二重管理はこの値と migrations パスのみ）。
                        'version_order' => 'creation',
                    ]));
                    // .env は toolkit の EnvironmentWriter で原子書き込みする。
                    // 従来の heredoc 生補間と違い値をエスケープするため、パスワード等に
                    // " $ # 空白が含まれても .env が壊れない（invoice の潜在バグを解消）。
                    (new EnvironmentWriter())->write(ENV_FILE, [
                        'APP_ENV' => 'production',
                        'APP_DEBUG' => 'false',
                        'APP_NAME' => 'NeNe Invoice',
                        'PROBLEM_DETAILS_BASE_URL' => 'https://nene-invoice.dev/problems/',
                        'TENANT_RESOLUTION' => $tenantResolution,
                        'ORG_SLUG' => '',
                        'BASE_DOMAIN' => $envBaseDomain,
                        'NENE2_LOCAL_JWT_SECRET' => EnvironmentWriter::generateSecret(32),
                        'DB_ADAPTER' => 'mysql',
                        'DB_NAME' => $dbName,
                        'DB_HOST' => $dbHost,
                        'DB_PORT' => (string) $dbPort,
                        'DB_USER' => $dbUser,
                        'DB_PASSWORD' => $dbPass,
                        'DB_CHARSET' => 'utf8mb4',
                    ]);

                    header('Location: install.php?step=2');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'DB 接続エラー: ' . $e->getMessage();
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($step === 2) {
            // 管理者作成＝不可逆な最終ミューテーションの直前に、再設置を最終
            // 確認する（冒頭ガード通過後に並行タブ等で設置が完了した場合の
            // 二重プロビジョニング防止）。文言は冒頭ガードと同一。
            $blockedReason = $reinstallGuard->blockedReason();

            if ($blockedReason !== null) {
                refuseInstall($blockedReason === 'marker_present'
                    ? 'インストール済みです。install.php を削除してください。'
                    : '既にプロビジョニング済みのデータベースが検出されました。再インストールはできません。install.php を削除してください。');
            }

            // 管理者ユーザー作成。利用形態を .env から読み、single/multi で分岐。
            $adminEmail    = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';
            $companyName   = trim($_POST['company_name'] ?? '');

            // 会社名は single のみ必須（multi では組織はアプリ内で superadmin が作る）。
            if (!$isMultiInstall && $companyName === '') {
                $fieldErrors['company'] = '会社名を入力してください。';
            }
            if ($adminEmail === '') {
                $fieldErrors['email'] = 'メールアドレスを入力してください。';
            }
            if ($adminPassword === '') {
                $fieldErrors['pw'] = 'パスワードを入力してください。';
            }

            if ($fieldErrors === []) {
                if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $fieldErrors['email'] = '有効なメールアドレスを入力してください。';
                }
                if (strlen($adminPassword) < 12) {
                    $fieldErrors['pw'] = 'パスワードは 12 文字以上にしてください。';
                }
            }

            if ($fieldErrors !== []) {
                $errors[] = '入力内容に誤りがあります。';
            }

            if ($errors === [] && $fieldErrors === []) {
                if (!file_exists(ENV_FILE)) {
                    $errors[] = '.env ファイルが見つかりません。ステップ 1 からやり直してください。';
                } else {
                    try {
                        $env = parse_ini_file(ENV_FILE);

                        if ($env === false) {
                            throw new RuntimeException('.env ファイルを読み込めませんでした。');
                        }

                        // 分岐の権威は書き込み済み .env の TENANT_RESOLUTION。
                        $isSingle = (string) ($env['TENANT_RESOLUTION'] ?? 'single') === 'single';

                        // 生 SQL をやめ、テスト済みユースケースへ委譲する（S3-2 #579）。
                        // container を boot し、書き込み済み .env の DB 設定で組織/管理者を
                        // 作成する。single は CreateOrganizationUseCase の org+admin 原子生成
                        // （監査つき）を再利用し company_settings をシード、multi は
                        // superadmin（organization_id=NULL）を作成する。
                        $container = (new RuntimeContainerFactory(ROOT))->create();

                        $createOrganization = $container->get(CreateOrganizationUseCaseInterface::class);
                        $queryExecutor      = $container->get(DatabaseQueryExecutorInterface::class);

                        if (
                            !$createOrganization instanceof CreateOrganizationUseCaseInterface
                            || !$queryExecutor instanceof DatabaseQueryExecutorInterface
                        ) {
                            throw new RuntimeException('アプリケーションコンテナの構成が正しくありません。');
                        }

                        $orgSlug = $isSingle
                            ? (preg_replace('/[^a-z0-9\-]/', '-', strtolower($companyName)) ?: 'default')
                            : '';

                        (new InstallApplication(
                            $createOrganization,
                            new PdoOrganizationRepository($queryExecutor, new UtcClock()),
                            new PdoInstallProvisioningRepository($queryExecutor, new UtcClock()),
                        ))->install(new InstallConfig(
                            isSingle: $isSingle,
                            organizationName: $companyName,
                            organizationSlug: $orgSlug,
                            adminEmail: $adminEmail,
                            adminPassword: $adminPassword,
                        ));

                        $reinstallGuard->markInstalled(date('c'));

                        $success = true;
                    } catch (PDOException $e) {
                        $errors[] = 'データベースエラー: ' . $e->getMessage();
                    } catch (\Throwable $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }
    }

    // 表示する画面を決定。要件不合格なら常に要件画面でブロック。
    if ($reqErrors !== []) {
        $view = 'requirements';
    } elseif ($success) {
        $view = 'complete';
    } elseif ($step === 2) {
        $view = 'admin';
    } elseif ($step === 1) {
        $view = 'database';
    } else {
        $view = 'requirements';
    }
}

// ステッパー位置（0=DB / 1=管理者 / 2=完了）。要件・DB・取得は 0。
$stepIdx = match ($view) {
    'admin' => 1,
    'complete' => 2,
    default => 0,
};
$vsteps = [
    ['t' => 'データベース', 'd' => '接続情報の入力'],
    ['t' => '管理者設定', 'd' => 'アカウント作成'],
    ['t' => '完了', 'd' => 'セットアップ終了'],
];

// レンタルサーバーのホスト記入例プリセット（チップ）。
$hosts = [
    ['id' => 'sakura', 'label' => 'さくら', 'host' => 'mysqlXXX.db.sakura.ne.jp', 'db' => 'yourname_invoice', 'user' => 'yourname', 'note' => '「データベース」→ 該当 DB の「データベースサーバ」欄がホスト名です。'],
    ['id' => 'heteml', 'label' => 'ヘテムル', 'host' => 'mysqlXXX.heteml.lib', 'db' => '_invoice', 'user' => '_user', 'note' => '「データベース」→「データベース一覧」の「ホスト名（DB サーバー）」を使います。'],
    ['id' => 'xserver', 'label' => 'エックスサーバー', 'host' => 'mysqlXXXX.xserver.jp', 'db' => 'yourid_invoice', 'user' => 'yourid_user', 'note' => 'サーバーパネル「MySQL 設定」→「MySQL ホスト名」を確認してください。'],
    ['id' => 'conoha', 'label' => 'ConoHa WING', 'host' => 'mysqlXXX.conoha.ne.jp', 'db' => 'yourname_invoice', 'user' => 'yourname', 'note' => '「データベース」→ 対象 DB の「ホスト名」をコピーしてください。'],
    ['id' => 'other', 'label' => 'その他 / わからない', 'host' => 'localhost', 'db' => 'yourname_invoice', 'user' => 'yourname', 'note' => '契約中のレンタルサーバー管理画面（コントロールパネル）の「データベース」欄で確認できます。'],
];

$hasError = $errors !== [] || $fieldErrors !== [];

// POST 値の復元用ヘルパー
$old = static fn (string $k, string $default = ''): string => h($_POST[$k] ?? $default);

// 利用形態フォームの復元値（DB ステップ再表示時）。
$tmOld  = ($_POST['tenant_mode'] ?? 'single') === 'multi' ? 'multi' : 'single';
$resOld = (string) ($_POST['tenant_resolution'] ?? 'path');
if (!in_array($resOld, ['path', 'subdomain', 'custom_domain'], true)) {
    $resOld = 'path';
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NeNe Invoice — セットアップウィザード</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 42 42'%3E%3Crect width='42' height='42' fill='%23275245'/%3E%3Ctext x='-2' y='32' font-family='sans-serif' font-weight='800' font-size='32' fill='%23ffffff' opacity='0.4'%3EN%3C/text%3E%3Ctext x='11' y='32' font-family='sans-serif' font-weight='800' font-size='32' fill='%23ffffff'%3EN%3C/text%3E%3C/svg%3E">
<style>
/* NeNe Invoice — セットアップウィザード（ClaudeDesign 準拠 / deep-green SaaS） */
:root{
  --bg:oklch(98% 0.0025 175);--surface:oklch(100% 0 0);--surface-2:oklch(97.4% 0.0035 175);
  --surface-sunk:oklch(96.2% 0.004 175);--border:oklch(90.5% 0.006 172);--border-strong:oklch(83.5% 0.008 172);
  --fg:oklch(27% 0.011 172);--fg-muted:oklch(50% 0.01 172);--fg-subtle:oklch(62% 0.008 172);--fg-faint:oklch(73% 0.007 172);
  --brand:oklch(41% 0.046 167);--brand-strong:oklch(34% 0.045 168);--brand-deep:oklch(28% 0.036 170);
  --brand-soft:oklch(93.5% 0.018 167);--brand-softer:oklch(97% 0.01 167);--on-brand:oklch(98.5% 0.006 165);--link:oklch(46% 0.055 215);
  --ok:oklch(47% 0.07 155);--ok-soft:oklch(94.5% 0.028 155);--danger:oklch(49% 0.115 28);--danger-soft:oklch(95% 0.03 28);
  --warn:oklch(55% 0.075 75);--warn-soft:oklch(95% 0.035 80);--info:oklch(48% 0.055 235);--info-soft:oklch(95% 0.025 235);
  --side-brand:oklch(93% 0.025 158);
  --radius:8px;--radius-sm:6px;--radius-lg:12px;
  --shadow-card:0 1px 2px oklch(30% 0.02 165 / 0.06);
  --shadow-pop:0 8px 28px oklch(28% 0.03 165 / 0.16), 0 2px 6px oklch(28% 0.03 165 / 0.08);
  --ring:0 0 0 3px oklch(43% 0.072 162 / 0.20);
  --font-sans:"Noto Sans JP",system-ui,sans-serif;--font-serif:"Noto Serif JP",serif;--font-num:"Roboto Mono","Noto Sans JP",monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family:var(--font-sans);background:var(--bg);color:var(--fg);line-height:1.6;-webkit-font-smoothing:antialiased;font-feature-settings:"palt" 1}
code{font-family:var(--font-num)}
::selection{background:color-mix(in oklch,var(--brand) 24%,transparent)}
.iz{min-height:100vh;background:var(--bg)}
.iz-stage{display:grid;grid-template-columns:minmax(380px,0.92fr) 1.08fr;min-height:100vh}
.iz-aside{position:relative;overflow:hidden;color:oklch(95% 0.012 160);background:linear-gradient(157deg,var(--brand-strong) 0%,var(--brand-deep) 60%,oklch(24% 0.03 172) 100%);display:flex;flex-direction:column;justify-content:space-between;padding:48px 46px 38px}
.iz-aside::before{content:"";position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(oklch(100% 0 0 / 0.05) 1px,transparent 1px),linear-gradient(90deg,oklch(100% 0 0 / 0.05) 1px,transparent 1px);background-size:40px 40px;mask-image:linear-gradient(150deg,#000 8%,transparent 80%)}
.iz-aside::after{content:"";position:absolute;right:-80px;bottom:-100px;width:440px;height:440px;background:radial-gradient(circle at 30% 30%,oklch(100% 0 0 / 0.06),transparent 60%);pointer-events:none}
.iz-aside>*{position:relative;z-index:1}
.iz-bs-top{display:flex;align-items:center;gap:13px}
.mono-mark{width:38px;height:34px;flex:none;color:var(--side-brand);display:block}
.mono-mark svg{width:100%;height:100%;display:block}
.iz-bs-top .abt-name{font-size:18px;font-weight:700;color:#fff;letter-spacing:.01em}
.iz-bs-top .abt-sub{font-size:10.5px;color:oklch(80% 0.02 160);letter-spacing:.16em;text-transform:uppercase;margin-top:3px}
.iz-bs-mid{max-width:400px}
.iz-bs-mid h2{font-family:var(--font-serif);font-size:25px;font-weight:700;line-height:1.5;letter-spacing:.01em;color:#fff;text-wrap:balance}
.iz-bs-mid .lead{font-size:13px;color:oklch(84% 0.02 160);margin-top:14px;line-height:1.9}
.vstep{list-style:none;margin:30px 0 0;padding:0}
.vstep li{display:flex;gap:14px;padding-bottom:4px}
.vstep .vs-rail{display:flex;flex-direction:column;align-items:center;flex:none}
.vstep .vs-dot{width:30px;height:30px;border-radius:50%;flex:none;display:grid;place-items:center;font-family:var(--font-num);font-size:13px;font-weight:600;background:oklch(100% 0 0 / 0.10);color:oklch(86% 0.02 160);border:1px solid oklch(100% 0 0 / 0.20);transition:all .2s}
.vstep .vs-dot svg{width:14px;height:14px}
.vstep .vs-line{width:2px;flex:1;min-height:22px;background:oklch(100% 0 0 / 0.16);margin:4px 0}
.vstep li:last-child .vs-line{display:none}
.vstep .vs-body{padding-top:3px;padding-bottom:18px;flex:1;min-width:0}
.vstep .vs-t{font-size:14px;font-weight:600;color:oklch(92% 0.015 160)}
.vstep .vs-d{font-size:11.5px;color:oklch(74% 0.02 160);margin-top:2px;white-space:nowrap}
.vstep li.active .vs-dot{background:var(--side-brand);color:var(--brand-deep);border-color:var(--side-brand);box-shadow:0 0 0 5px oklch(100% 0 0 / 0.08)}
.vstep li.active .vs-t{color:#fff}
.vstep li.done .vs-dot{background:oklch(100% 0 0 / 0.16);color:var(--side-brand);border-color:oklch(100% 0 0 / 0.28)}
.vstep li.done .vs-line{background:var(--side-brand);opacity:.6}
.iz-bs-foot .iz-trust{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.iz-trust .tb{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:600;color:oklch(88% 0.015 160);background:oklch(100% 0 0 / 0.08);border:1px solid oklch(100% 0 0 / 0.14);border-radius:999px;padding:4px 11px}
.iz-trust .tb svg{width:12px;height:12px;color:var(--side-brand)}
.iz-bs-foot .copy{font-size:10.5px;color:oklch(72% 0.02 160)}
.iz-main{background:var(--surface);display:grid;place-items:start center;padding:46px 40px;overflow-y:auto}
.iz-form{width:100%;max-width:460px}
.hstep{display:none;gap:8px;margin-bottom:26px}
.hstep .hs{flex:1;text-align:center;font-size:11.5px;font-weight:600;padding:8px 4px;border-radius:var(--radius-sm);color:var(--fg-faint);background:var(--surface-sunk);border:1px solid var(--border)}
.hstep .hs.active{background:var(--brand);color:var(--on-brand);border-color:var(--brand)}
.hstep .hs.done{background:var(--brand-soft);color:var(--brand-strong);border-color:color-mix(in oklch,var(--brand) 24%,transparent)}
.hstep .hs.done::before{content:"✓ "}
.iz-head{font-family:var(--font-serif);font-size:23px;font-weight:700;letter-spacing:.005em;color:var(--fg)}
.iz-headsub{font-size:13px;color:var(--fg-muted);margin:7px 0 24px;line-height:1.8}
.iz-headsub b{color:var(--fg);font-weight:600}
.field{margin-bottom:16px}
.field:last-of-type{margin-bottom:0}
.label{display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:12.5px;font-weight:600;color:var(--fg);margin-bottom:6px}
.label .req{color:var(--danger);font-weight:700}
.label .opt{color:var(--fg-muted);font-weight:400;font-size:11px}
.input{display:block;width:100%;font-family:inherit;font-size:13.5px;color:var(--fg);background:var(--surface);border:1px solid var(--border-strong);border-radius:var(--radius-sm);padding:10px 12px;transition:border-color .12s,box-shadow .12s}
.input.mono{font-family:var(--font-num)}
.input::placeholder{color:var(--fg-faint)}
.input:focus{outline:none;border-color:var(--brand);box-shadow:var(--ring)}
.input.is-error{border-color:var(--danger);background:color-mix(in oklch,var(--danger) 5%,var(--surface))}
.hint{font-size:11.5px;color:var(--fg-muted);margin-top:6px;line-height:1.65}
.hint code,.hint b{color:var(--fg)}
.hint code{font-size:.92em;background:var(--surface-sunk);border:1px solid var(--border);border-radius:4px;padding:.5px 5px}
.err-text{font-size:11.5px;color:var(--danger);margin-top:6px;font-weight:600;display:flex;align-items:center;gap:5px}
.err-text svg{width:13px;height:13px;flex:none}
.form-row2{display:grid;grid-template-columns:1fr 116px;gap:12px}
.pw-wrap{position:relative}
.pw-wrap .input{padding-right:42px}
.pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:30px;height:30px;display:grid;place-items:center;background:transparent;border:0;color:var(--fg-faint);cursor:pointer;border-radius:var(--radius-sm)}
.pw-eye:hover{color:var(--fg-muted);background:var(--surface-sunk)}
.pw-eye svg{width:17px;height:17px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:var(--radius-sm);font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;padding:11px 18px;border:1px solid transparent;line-height:1;transition:background .12s,box-shadow .12s,color .12s}
.btn svg{width:16px;height:16px}
.btn-block{width:100%}
.btn-primary{background:var(--brand);color:var(--on-brand)}
.btn-primary:hover{background:var(--brand-strong)}
.btn-primary:disabled{opacity:.65;cursor:progress}
.btn-ghost{background:var(--surface);color:var(--fg);border-color:var(--border-strong)}
.btn-ghost:hover{background:var(--surface-2)}
.btn-row{display:flex;gap:10px;margin-top:24px}
.btn-row .btn{flex:1}
.btn-row .btn-back{flex:0 0 auto;padding-left:14px;padding-right:14px}
.btn-lg{padding:13px 20px;font-size:14px}
.linkbtn{background:none;border:0;color:var(--link);cursor:pointer;font:inherit;font-weight:600;font-size:12px;padding:0;display:inline-flex;align-items:center;gap:5px;text-decoration:none}
.linkbtn:hover{text-decoration:underline}
.linkbtn svg{width:13px;height:13px}
.tip{position:relative;display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;background:var(--border-strong);color:var(--surface);font-size:11px;font-weight:700;cursor:help;flex:none}
.tip:hover .tip-body,.tip:focus .tip-body{display:block}
.tip-body{display:none;position:absolute;z-index:20;left:50%;bottom:150%;transform:translateX(-50%);width:250px;background:oklch(20% 0.02 168);color:oklch(94% 0.01 165);font-size:11.5px;font-weight:400;line-height:1.6;padding:9px 11px;border-radius:var(--radius-sm);text-align:left;box-shadow:var(--shadow-pop)}
.tip-body code{color:oklch(86% 0.05 150);background:oklch(100% 0 0 / 0.08);padding:.5px 4px;border-radius:3px}
.tip-body::after{content:"";position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:oklch(20% 0.02 168)}
.alert{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:var(--radius-sm);font-size:12.5px;border:1px solid;margin-bottom:18px;line-height:1.65}
.alert>svg{width:17px;height:17px;flex:none;margin-top:1px}
.alert .a-body{flex:1;min-width:0}
.alert .a-title{font-weight:700;font-size:13px}
.alert .a-text{margin-top:3px;color:inherit;opacity:.92}
.alert.error{background:var(--danger-soft);color:var(--danger);border-color:color-mix(in oklch,var(--danger) 30%,transparent)}
.alert.ok{background:var(--ok-soft);color:var(--ok);border-color:color-mix(in oklch,var(--ok) 30%,transparent)}
.alert.warn{background:var(--warn-soft);color:color-mix(in oklch,var(--warn) 82%,var(--fg));border-color:color-mix(in oklch,var(--warn) 32%,transparent)}
.alert .det{margin-top:8px;font-family:var(--font-num);font-size:11px;background:color-mix(in oklch,var(--danger) 8%,transparent);border:1px solid color-mix(in oklch,var(--danger) 22%,transparent);border-radius:5px;padding:7px 9px;word-break:break-all}
.alert summary{cursor:pointer;font-weight:600;font-size:11.5px;margin-top:8px}
.reqs{list-style:none;margin:0;padding:0;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.reqs li{display:flex;gap:12px;padding:13px 15px;border-bottom:1px solid var(--border);align-items:flex-start}
.reqs li:last-child{border-bottom:none}
.reqs li.fail{background:color-mix(in oklch,var(--danger) 5%,transparent)}
.reqs .ic{width:22px;height:22px;flex:none;border-radius:50%;display:grid;place-items:center;margin-top:1px}
.reqs .ic svg{width:13px;height:13px}
.reqs .pass .ic{background:var(--ok-soft);color:var(--ok)}
.reqs .fail .ic{background:var(--danger-soft);color:var(--danger)}
.reqs .rq-t{font-size:13px;font-weight:600;color:var(--fg)}
.reqs .fail .rq-t{color:var(--danger)}
.reqs .rq-d{font-size:11.5px;color:var(--fg-muted);margin-top:2px;font-family:var(--font-num)}
.reqs .rq-fix{font-size:11.5px;color:var(--fg-muted);margin-top:7px;line-height:1.7;background:var(--surface-2);border:1px solid var(--border);border-radius:6px;padding:8px 10px}
.reqs .rq-fix b{color:var(--fg)}
.reqs .rq-fix code{font-family:var(--font-num);background:var(--surface-sunk);border:1px solid var(--border);border-radius:4px;padding:.5px 5px;font-size:.92em}
.reqs .rq-body{flex:1;min-width:0}
.host-help{margin-bottom:18px;padding:14px 15px;background:var(--brand-softer);border:1px solid color-mix(in oklch,var(--brand) 14%,var(--border));border-radius:var(--radius)}
.host-help .hh-q{font-size:12px;font-weight:600;color:var(--fg);display:flex;align-items:center;gap:7px}
.host-help .hh-q svg{width:15px;height:15px;color:var(--brand-strong);flex:none}
.host-help .hh-sub{font-size:11.5px;color:var(--fg-muted);margin:4px 0 11px}
.host-chips{display:flex;flex-wrap:wrap;gap:7px}
.host-chip{font-family:inherit;font-size:11.5px;font-weight:600;cursor:pointer;padding:6px 11px;border-radius:999px;background:var(--surface);border:1px solid var(--border-strong);color:var(--fg-muted);transition:all .12s}
.host-chip:hover{border-color:var(--brand);color:var(--brand-strong)}
.host-chip.on{background:var(--brand);color:var(--on-brand);border-color:var(--brand)}
.cp-toggle{margin-top:9px}
.cp-toggle svg{transition:transform .15s}
.cp-toggle.open svg{transform:rotate(180deg)}
.cp-diagram{margin-top:12px;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;background:var(--surface)}
.cp-bar{display:flex;align-items:center;gap:6px;padding:8px 11px;background:var(--surface-2);border-bottom:1px solid var(--border);font-size:11px;color:var(--fg-subtle)}
.cp-bar .dot{width:8px;height:8px;border-radius:50%;background:var(--border-strong)}
.cp-bar .cp-url{font-family:var(--font-num);font-size:10.5px;margin-left:6px}
.cp-grid{display:grid;grid-template-columns:116px 1fr;min-height:150px}
.cp-menu{background:var(--surface-sunk);border-right:1px solid var(--border);padding:8px 0}
.cp-menu .cp-mi{font-size:11px;color:var(--fg-muted);padding:7px 12px;display:flex;align-items:center;gap:7px}
.cp-menu .cp-mi.hot{background:var(--brand-soft);color:var(--brand-strong);font-weight:700;box-shadow:inset 3px 0 0 var(--brand)}
.cp-menu .cp-mi .cp-bullet{width:6px;height:6px;border-radius:2px;background:currentColor;opacity:.5;flex:none}
.cp-body{padding:13px 15px}
.cp-body .cp-h{font-size:11.5px;font-weight:700;color:var(--fg);margin-bottom:9px}
.cp-kv{display:grid;grid-template-columns:auto 1fr;gap:5px 12px;font-size:11px}
.cp-kv .k{color:var(--fg-muted);white-space:nowrap}
.cp-kv .v{font-family:var(--font-num);color:var(--fg);font-weight:600}
.cp-kv .v.hl{background:oklch(85% 0.16 95 / 0.5);border-radius:3px;padding:0 4px}
.cp-note{font-size:10.5px;color:var(--fg-subtle);margin-top:11px;line-height:1.6;border-top:1px dashed var(--border);padding-top:9px}
.iz-loading .ld-h{font-family:var(--font-serif);font-size:19px;font-weight:700;color:var(--fg)}
.iz-loading .ld-sub{font-size:12.5px;color:var(--fg-muted);margin:6px 0 22px}
.substeps{list-style:none;margin:0;padding:0}
.substeps li{display:flex;align-items:center;gap:13px;padding:12px 0;border-bottom:1px solid var(--border)}
.substeps li:last-child{border-bottom:none}
.ss-ic{width:26px;height:26px;flex:none;border-radius:50%;display:grid;place-items:center}
.ss-pending .ss-ic{background:var(--surface-sunk);border:1px solid var(--border);color:var(--fg-faint)}
.ss-pending .ss-ic::after{content:"";width:7px;height:7px;border-radius:50%;background:var(--fg-faint);opacity:.5}
.ss-active .ss-ic{background:var(--brand-soft)}
.ss-done .ss-ic{background:var(--ok);color:#fff}
.ss-done .ss-ic svg{width:14px;height:14px}
.ss-t{font-size:13px;font-weight:600;color:var(--fg)}
.ss-pending .ss-t{color:var(--fg-faint);font-weight:500}
.ss-active .ss-t{color:var(--brand-strong)}
.ss-d{font-size:11px;color:var(--fg-muted);margin-top:1px}
.ss-pending .ss-d{color:var(--fg-faint)}
.ss-meta{margin-left:auto;font-size:10.5px;color:var(--fg-faint);font-family:var(--font-num)}
.ss-active .ss-meta{color:var(--brand-strong)}
.spinner{width:15px;height:15px;border:2px solid color-mix(in oklch,var(--brand) 30%,transparent);border-top-color:var(--brand);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.ld-warn{display:flex;gap:8px;align-items:center;margin-top:22px;font-size:11.5px;color:var(--fg-subtle);background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:9px 12px}
.ld-warn svg{width:14px;height:14px;flex:none;color:var(--warn)}
.ld-bar{height:5px;border-radius:999px;background:var(--surface-sunk);border:1px solid var(--border);overflow:hidden;margin-bottom:22px}
.ld-bar>span{display:block;height:100%;background:var(--brand);transition:width .5s ease;width:0}
.done-mark{width:60px;height:60px;border-radius:50%;margin:4px 0 18px;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;animation:pop .4s cubic-bezier(.2,.9,.3,1.4)}
.done-mark svg{width:30px;height:30px}
@keyframes pop{from{transform:scale(.4);opacity:0}to{transform:scale(1);opacity:1}}
.done-title{font-family:var(--font-serif);font-size:25px;font-weight:700;color:var(--fg)}
.done-sub{font-size:13px;color:var(--fg-muted);margin:7px 0 22px}
.sec-warn{display:flex;gap:12px;align-items:flex-start;padding:15px 16px;border-radius:var(--radius);border:1px solid color-mix(in oklch,var(--danger) 34%,transparent);background:var(--danger-soft);margin-bottom:22px}
.sec-warn>.sw-ico{width:34px;height:34px;flex:none;border-radius:50%;background:color-mix(in oklch,var(--danger) 16%,transparent);color:var(--danger);display:grid;place-items:center}
.sec-warn .sw-ico svg{width:19px;height:19px}
.sec-warn .sw-t{font-size:13.5px;font-weight:700;color:var(--danger)}
.sec-warn .sw-d{font-size:12px;color:color-mix(in oklch,var(--danger) 78%,var(--fg));margin-top:4px;line-height:1.7}
.sec-warn .sw-d code{font-family:var(--font-num);background:color-mix(in oklch,var(--danger) 12%,transparent);border-radius:4px;padding:.5px 5px}
.next-h{font-size:12px;font-weight:700;color:var(--fg-muted);letter-spacing:.04em;text-transform:uppercase;margin-bottom:10px}
.next-list{list-style:none;margin:0 0 24px;padding:0}
.next-list li{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;color:var(--fg);align-items:flex-start}
.next-list li:last-child{border-bottom:none}
.next-list .nl-n{width:22px;height:22px;flex:none;border-radius:50%;background:var(--brand-soft);color:var(--brand-strong);display:grid;place-items:center;font-size:11.5px;font-weight:700;font-family:var(--font-num);margin-top:1px}
.next-list .nl-d{font-size:11.5px;color:var(--fg-muted);margin-top:2px}
/* 利用形態（single/multi）＋解決方式（Feature A） */
.tenant-sec{margin:6px 0 20px;padding:15px 15px 4px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius)}
.tenant-sec .ts-h{font-size:12.5px;font-weight:700;color:var(--fg);display:flex;align-items:center;gap:6px;margin-bottom:10px}
.tenant-sec .ts-h svg{width:15px;height:15px;color:var(--brand-strong);flex:none}
.opt-card{display:flex;gap:11px;align-items:flex-start;padding:11px 13px;border:1px solid var(--border-strong);border-radius:var(--radius-sm);background:var(--surface);cursor:pointer;margin-bottom:9px;transition:border-color .12s,box-shadow .12s,background .12s}
.opt-card:hover{border-color:var(--brand)}
.opt-card input{margin-top:2px;accent-color:var(--brand);flex:none;width:15px;height:15px}
.opt-card.on{border-color:var(--brand);background:var(--brand-softer);box-shadow:var(--ring)}
.opt-card .oc-t{font-size:13px;font-weight:600;color:var(--fg)}
.opt-card .oc-d{font-size:11.5px;color:var(--fg-muted);margin-top:2px;line-height:1.6}
.opt-card .oc-badge{display:inline-block;font-size:10px;font-weight:700;color:var(--brand-strong);background:var(--brand-soft);border-radius:999px;padding:1px 8px;margin-left:6px;vertical-align:middle}
.select{display:block;width:100%;font-family:inherit;font-size:13.5px;color:var(--fg);background:var(--surface);border:1px solid var(--border-strong);border-radius:var(--radius-sm);padding:10px 12px}
.select:focus{outline:none;border-color:var(--brand);box-shadow:var(--ring)}
.res-hint{font-size:11.5px;color:var(--fg-muted);margin-top:7px;line-height:1.65;display:flex;gap:6px;align-items:flex-start}
.res-hint svg{width:13px;height:13px;flex:none;margin-top:2px;color:var(--brand-strong)}
/* アップロード取得（Feature B） */
.up-drop{border:1.5px dashed var(--border-strong);border-radius:var(--radius);background:var(--surface-2);padding:20px 16px;text-align:center;cursor:pointer;transition:border-color .12s,background .12s}
.up-drop:hover{border-color:var(--brand);background:var(--brand-softer)}
.up-drop.has-file{border-color:var(--brand);border-style:solid;background:var(--brand-softer)}
.up-drop .ud-ic{width:34px;height:34px;margin:0 auto 8px;color:var(--brand-strong)}
.up-drop .ud-ic svg{width:100%;height:100%}
.up-drop .ud-t{font-size:13px;font-weight:600;color:var(--fg)}
.up-drop .ud-d{font-size:11.5px;color:var(--fg-muted);margin-top:3px}
.up-drop .ud-file{font-family:var(--font-num);font-size:11.5px;color:var(--brand-strong);font-weight:600;margin-top:6px;word-break:break-all}
.up-drop input[type=file]{display:none}
[hidden]{display:none !important}
@media (max-width:900px){
  .iz-stage{grid-template-columns:1fr}
  .iz-aside{padding:30px 26px 26px;flex-direction:row;align-items:center;justify-content:space-between;gap:16px}
  .iz-aside .iz-bs-mid,.iz-aside .iz-bs-foot,.vstep{display:none}
  .iz-main{padding:32px 22px 48px}
  .hstep{display:flex}
}
@media (max-width:520px){.form-row2{grid-template-columns:1fr}.iz-head,.done-title{font-size:21px}}
@media (prefers-reduced-motion:reduce){.done-mark{animation:none}}
</style>
</head>
<body data-view="<?= h($view) ?>" data-error="<?= $hasError ? '1' : '0' ?>">
<div class="iz">
  <div class="iz-stage">

    <aside class="iz-aside">
      <div class="iz-bs-top">
        <span class="mono-mark"><?= ico('mono') ?></span>
        <div><div class="abt-name">NeNe Invoice</div><div class="abt-sub">Setup Wizard</div></div>
      </div>
      <div class="iz-bs-mid">
        <h2>請求業務を、<br>自分の手元で始める。</h2>
        <p class="lead">適格請求書（インボイス）対応の見積・請求・入金管理。専門知識がなくても、3 ステップでセットアップが完了します。</p>
        <ul class="vstep">
          <?php foreach ($vsteps as $i => $s): ?>
            <li class="<?= $i === $stepIdx ? 'active' : ($i < $stepIdx ? 'done' : '') ?>">
              <div class="vs-rail"><span class="vs-dot"><?= $i < $stepIdx ? ico('check') : ($i + 1) ?></span><span class="vs-line"></span></div>
              <div class="vs-body"><div class="vs-t"><?= h($s['t']) ?></div><div class="vs-d"><?= h($s['d']) ?></div></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="iz-bs-foot">
        <div class="iz-trust">
          <span class="tb"><?= ico('shield') ?>適格請求書対応</span>
          <span class="tb"><?= ico('server') ?>セルフホスト</span>
          <span class="tb"><?= ico('oss') ?>オープンソース（MIT）</span>
        </div>
        <div class="copy">© 2026 NeNe Invoice — install.php</div>
      </div>
    </aside>

    <div class="iz-main">
      <div class="iz-form" id="izView">
        <div class="hstep">
          <?php foreach ($vsteps as $i => $s): ?>
            <div class="hs <?= $i === $stepIdx ? 'active' : ($i < $stepIdx ? 'done' : '') ?>"><?= ($i + 1) . '. ' . h($s['t']) ?></div>
          <?php endforeach; ?>
        </div>

        <?php if ($view === 'acquire'): ?>
        <div class="iz-head">アプリの取得（アップロード）</div>
        <div class="iz-headsub">アプリ本体（<code>vendor/</code> など）がまだ展開されていません。<b>公式配布元からダウンロードした ZIP</b> をアップロードして展開します。</div>

        <?php if ($errors !== []): ?>
          <div class="alert error"><?= ico('warn') ?><div class="a-body"><div class="a-title">アップロードを処理できませんでした</div><div class="a-text"><?= h(implode(' ', $errors)) ?></div></div></div>
        <?php endif; ?>

        <?php if ($reqErrors !== []): ?>
          <div class="alert error"><?= ico('warn') ?><div class="a-body"><div class="a-title">展開に必要な条件が不足しています</div><div class="a-text">以下を解消してから、ページを再読み込みしてください。</div></div></div>
        <?php endif; ?>

        <ul class="reqs" style="margin-bottom:20px">
          <?php foreach ($checks as $c): ?>
            <li class="<?= $c['ok'] ? 'pass' : 'fail' ?>">
              <span class="ic"><?= $c['ok'] ? ico('check') : ico('x') ?></span>
              <div class="rq-body">
                <div class="rq-t"><?= h($c['label']) ?></div>
                <div class="rq-d"><?= h($c['detail']) ?></div>
                <?php if (!$c['ok']): ?><div class="rq-fix"><b>解決方法:</b> <?= $c['fix'] ?></div><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($reqErrors === []): ?>
        <form method="post" action="install.php" id="acquireForm" enctype="multipart/form-data">
          <input type="hidden" name="action" value="acquire">

          <div class="field">
            <label class="label">配布 ZIP ファイル<span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body">NeNe Invoice の公式リリースページからダウンロードした <code>nene-invoice-*.zip</code> を選んでください。他のファイルはアップロードしないでください。</span></span>
            </label>
            <label class="up-drop" id="upDrop" for="payloadFile">
              <span class="ud-ic"><?= ico('upload') ?></span>
              <span class="ud-t">ZIP ファイルを選択</span>
              <span class="ud-d">クリックして <code>nene-invoice-*.zip</code> を選択（.zip のみ）</span>
              <span class="ud-file" id="upFileName" hidden></span>
              <input type="file" id="payloadFile" name="payload" accept=".zip,application/zip">
            </label>
            <p class="hint"><b>公式配布元からダウンロードした ZIP のみを使用してください。</b> 出所不明の ZIP はアップロードしないでください。</p>
          </div>

          <div class="field">
            <label class="label" for="expected_sha256">期待する SHA-256<span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body">公式リリースページに記載されている ZIP の SHA-256（64 桁の 16 進数）を貼り付けてください。アップロードしたファイルのハッシュと照合し、一致した場合のみ展開します。</span></span>
            </label>
            <input id="expected_sha256" name="expected_sha256" class="input mono" value="<?= $old('expected_sha256') ?>" placeholder="例: d27ef479…（64 桁の 16 進数）" autocomplete="off" spellcheck="false" required>
            <p class="hint">公式リリースページ記載の <b>SHA-256</b> を貼り付けます。展開の<b>前</b>にハッシュを照合します（不一致なら展開しません）。<br>この段階では<b>署名検証は行いません</b>（配布元の SHA-256 照合のみ）。</p>
          </div>

          <div class="btn-row">
            <button type="submit" class="btn btn-primary btn-block">アップロードして展開<?= ico('arrow') ?></button>
          </div>
        </form>
        <?php else: ?>
        <div class="btn-row">
          <a class="btn btn-primary btn-block" href="install.php">再読み込みして再チェック</a>
        </div>
        <?php endif; ?>

        <?php elseif ($view === 'requirements'): ?>
        <div class="iz-head">サーバー要件の確認</div>
        <div class="iz-headsub">インストールを始める前に、サーバーが NeNe Invoice の動作条件を満たしているか確認します。</div>

        <?php if ($reqErrors === []): ?>
          <div class="alert ok"><?= ico('check') ?><div class="a-body"><div class="a-title">すべての要件を満たしています</div><div class="a-text">このサーバーでインストールを続行できます。</div></div></div>
        <?php else: ?>
          <div class="alert error"><?= ico('warn') ?><div class="a-body"><div class="a-title">要件チェックに失敗しました</div><div class="a-text">以下を解消してから、ページを再読み込みしてください。解決後にセットアップを続行できます。</div></div></div>
        <?php endif; ?>

        <ul class="reqs">
          <?php foreach ($checks as $c): ?>
            <li class="<?= $c['ok'] ? 'pass' : 'fail' ?>">
              <span class="ic"><?= $c['ok'] ? ico('check') : ico('x') ?></span>
              <div class="rq-body">
                <div class="rq-t"><?= h($c['label']) ?></div>
                <div class="rq-d"><?= h($c['detail']) ?></div>
                <?php if (!$c['ok']): ?><div class="rq-fix"><b>解決方法:</b> <?= $c['fix'] ?></div><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="btn-row">
          <?php if ($reqErrors === []): ?>
            <a class="btn btn-primary btn-block" href="install.php?step=1">セットアップを開始<?= ico('arrow') ?></a>
          <?php else: ?>
            <a class="btn btn-primary btn-block" href="install.php">再読み込みして再チェック</a>
          <?php endif; ?>
        </div>

        <?php elseif ($view === 'database'): ?>
        <div class="iz-head">データベースに接続</div>
        <div class="iz-headsub">MySQL の接続情報を入力してください。これらは契約中の<b>レンタルサーバー管理画面（コントロールパネル）の「データベース」欄</b>で確認できます。</div>

        <?php if ($errors !== []): ?>
          <div class="alert error"><?= ico('warn') ?><div class="a-body"><div class="a-title">データベースに接続できませんでした</div><div class="a-text">ホスト名・ポート・ユーザー名・パスワードをご確認ください。共有サーバーではホスト名が <code>localhost</code> ではなく専用ホスト名のことが多いです。</div><details><summary>技術的な詳細を表示</summary><div class="det"><?= h(implode("\n", $errors)) ?></div></details></div></div>
        <?php endif; ?>

        <div class="host-help">
          <div class="hh-q"><?= ico('help') ?>お使いのレンタルサーバーは？</div>
          <div class="hh-sub">選ぶと、ホスト名の<b>記入例</b>を自動入力します（実際の値はコントロールパネルでご確認ください）。</div>
          <div class="host-chips" id="hostChips">
            <?php foreach ($hosts as $hh): ?>
              <button type="button" class="host-chip" data-id="<?= h($hh['id']) ?>" data-host="<?= h($hh['host']) ?>" data-db="<?= h($hh['db']) ?>" data-user="<?= h($hh['user']) ?>" data-note="<?= h($hh['note']) ?>"><?= h($hh['label']) ?></button>
            <?php endforeach; ?>
          </div>
          <button type="button" class="linkbtn cp-toggle" id="cpToggle">コントロールパネルのどこを見る？</button>
          <div class="cp-diagram" id="cpDiagram" hidden>
            <div class="cp-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="cp-url">https://cp.your-host.example/database</span></div>
            <div class="cp-grid">
              <div class="cp-menu">
                <div class="cp-mi"><span class="cp-bullet"></span>ドメイン</div>
                <div class="cp-mi"><span class="cp-bullet"></span>メール</div>
                <div class="cp-mi hot"><span class="cp-bullet"></span>データベース</div>
                <div class="cp-mi"><span class="cp-bullet"></span>FTP</div>
                <div class="cp-mi"><span class="cp-bullet"></span>SSL</div>
              </div>
              <div class="cp-body">
                <div class="cp-h">データベース情報</div>
                <div class="cp-kv">
                  <span class="k">ホスト名</span><span class="v hl" id="cpHost">localhost</span>
                  <span class="k">データベース名</span><span class="v" id="cpDb">yourname_invoice</span>
                  <span class="k">ユーザー名</span><span class="v" id="cpUser">yourname</span>
                  <span class="k">ポート</span><span class="v">3306</span>
                </div>
                <div class="cp-note" id="cpNote">契約中のレンタルサーバー管理画面（コントロールパネル）の「データベース」欄で確認できます。 黄色の<b>ホスト名</b>を下のフォームにそのまま貼り付けてください。</div>
              </div>
            </div>
          </div>
        </div>

        <form method="post" action="install.php?step=1" id="dbForm">
          <div class="form-row2">
            <div class="field">
              <label class="label" for="db_host">ホスト<span class="req">*</span>
                <span class="tip" tabindex="0">?<span class="tip-body">データベースサーバーのアドレス。共有ホスティングでは <code>localhost</code> ではなく専用ホスト名（例 mysqlXXX.example.ne.jp）のことが多いです。</span></span>
              </label>
              <input id="db_host" name="db_host" class="input mono" value="<?= $old('db_host', 'localhost') ?>" placeholder="例: mysqlXXX.db.sakura.ne.jp">
            </div>
            <div class="field">
              <label class="label" for="db_port">ポート<span class="req">*</span>
                <span class="tip" tabindex="0">?<span class="tip-body">通常は MySQL 既定の <code>3306</code> のままで問題ありません。</span></span>
              </label>
              <input id="db_port" name="db_port" class="input mono" value="<?= $old('db_port', '3306') ?>">
            </div>
          </div>
          <p class="hint" style="margin-top:-8px;margin-bottom:16px">初期値は <code>localhost</code> / <code>3306</code>。共有サーバーではホストを専用ホスト名に書き換えることが多いです。</p>

          <div class="field">
            <label class="label" for="db_name">データベース名<span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body">コントロールパネルで作成済みのデータベース名。空のデータベースを指定してください（既存データには触れません）。</span></span>
            </label>
            <input id="db_name" name="db_name" class="input mono" value="<?= $old('db_name') ?>" placeholder="例: yourname_invoice" required>
            <p class="hint">事前に作成した<b>空のデータベース</b>を指定します。テーブルはこのインストーラが作成します。</p>
          </div>

          <div class="field">
            <label class="label" for="db_user">ユーザー名<span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body">そのデータベースにアクセスできる MySQL ユーザー名。コントロールパネルの DB 情報に記載されています。</span></span>
            </label>
            <input id="db_user" name="db_user" class="input mono" value="<?= $old('db_user') ?>" placeholder="例: yourname_invoice" required>
          </div>

          <div class="field">
            <label class="label" for="db_pass">パスワード<span class="opt">（任意）</span>
              <span class="tip" tabindex="0">?<span class="tip-body">上記 MySQL ユーザーのパスワード。コントロールパネルで設定／確認できます。<b>NeNe Invoice のログインパスワードとは別物</b>です。</span></span>
            </label>
            <div class="pw-wrap">
              <input id="db_pass" name="db_pass" class="input mono" type="password" value="<?= $old('db_pass') ?>" placeholder="••••••••">
              <button type="button" class="pw-eye" data-pw="db_pass" tabindex="-1" aria-label="パスワード表示切替"><?= ico('eye') ?></button>
            </div>
            <p class="hint">サーバーの DB ユーザーのパスワード。<b>NeNe Invoice のログインパスワードとは別物</b>です。</p>
          </div>

          <div class="tenant-sec">
            <div class="ts-h"><?= ico('org') ?>利用形態</div>
            <label class="opt-card<?= $tmOld === 'single' ? ' on' : '' ?>" data-tenant="single">
              <input type="radio" name="tenant_mode" value="single"<?= $tmOld === 'single' ? ' checked' : '' ?>>
              <div>
                <div class="oc-t">単一組織（single）<span class="oc-badge">既定</span></div>
                <div class="oc-d">1 つの会社／組織だけで使う一般的な構成。管理者（admin）アカウントと会社情報を作成します。</div>
              </div>
            </label>
            <label class="opt-card<?= $tmOld === 'multi' ? ' on' : '' ?>" data-tenant="multi">
              <input type="radio" name="tenant_mode" value="multi"<?= $tmOld === 'multi' ? ' checked' : '' ?>>
              <div>
                <div class="oc-t">複数組織（multi）<span class="oc-badge">上級者向け</span></div>
                <div class="oc-d">複数の組織（テナント）を 1 つのインストールで運用します。全体管理者（superadmin）を作成します。<b>組織を管理する画面は現在未提供</b>のため、組織の作成・管理は API 経由（または managed / NeNe Suite 運用）が前提です（詳細は下記）。</div>
              </div>
            </label>

            <div id="multiOpts"<?= $tmOld === 'multi' ? '' : ' hidden' ?>>
              <div class="alert warn" style="margin-top:12px"><?= ico('warn') ?><div class="a-body"><div class="a-title">上級者向け構成です</div><div class="a-text">現在、<b>組織を作成・管理する管理画面（superadmin 用）は未提供</b>です。マルチテナントの組織作成・切替は <b>API 経由</b>（または managed / NeNe Suite 運用）が前提になります。1 事業者でお使いの場合は上の「単一組織」を選んでください。</div></div></div>
              <div class="field" style="margin-top:12px">
                <label class="label" for="tenant_resolution">組織の振り分け方式
                  <span class="tip" tabindex="0">?<span class="tip-body">URL からどの組織かを判別する方式です。あとで <code>.env</code> の <code>TENANT_RESOLUTION</code> で変更できます。</span></span>
                </label>
                <select id="tenant_resolution" name="tenant_resolution" class="select">
                  <option value="path"<?= $resOld === 'path' ? ' selected' : '' ?>>パス方式（/組織名/…）</option>
                  <option value="subdomain"<?= $resOld === 'subdomain' ? ' selected' : '' ?>>サブドメイン方式（組織名.ドメイン）</option>
                  <option value="custom_domain"<?= $resOld === 'custom_domain' ? ' selected' : '' ?>>独自ドメイン方式（組織ごとにドメイン）</option>
                </select>
                <div class="res-hint" data-res="path"<?= $resOld === 'path' ? '' : ' hidden' ?>><?= ico('check') ?><span><b>何が必要か:</b> DNS 設定不要。<code>/{org}/…</code> のパスで振り分けます。まず試すのに最適です。</span></div>
                <div class="res-hint" data-res="subdomain"<?= $resOld === 'subdomain' ? '' : ' hidden' ?>><?= ico('warn') ?><span><b>何が必要か:</b> ワイルドカード DNS（<code>*.ドメイン</code>）を 1 つのディレクトリに向ける設定が必要です。</span></div>
                <div class="res-hint" data-res="custom_domain"<?= $resOld === 'custom_domain' ? '' : ' hidden' ?>><?= ico('server') ?><span><b>何が必要か:</b> 組織ごとにドメインを用意し、それぞれをこのインストールに向けます。</span></div>
              </div>

              <div class="field" id="baseDomainField"<?= $resOld === 'subdomain' ? '' : ' hidden' ?>>
                <label class="label" for="base_domain">基準ドメイン（BASE_DOMAIN）<span class="req">*</span>
                  <span class="tip" tabindex="0">?<span class="tip-body">サブドメイン方式の親ドメイン。例: <code>example.com</code> とすると <code>acme.example.com</code> が組織 acme になります。</span></span>
                </label>
                <input id="base_domain" name="base_domain" class="input mono" value="<?= $old('base_domain') ?>" placeholder="例: example.com">
                <p class="hint">サブドメイン方式でのみ使用します（例: <code>example.com</code>）。</p>
              </div>
            </div>
          </div>

          <div class="btn-row">
            <a class="btn btn-ghost btn-back" href="install.php" aria-label="戻る"><?= ico('back') ?></a>
            <button type="submit" class="btn btn-primary">接続テスト＆スキーマ適用<?= ico('arrow') ?></button>
          </div>
        </form>

        <?php elseif ($view === 'admin'): ?>
        <div class="iz-head"><?= $isMultiInstall ? '全体管理者アカウントを作成' : '管理者アカウントを作成' ?></div>
        <?php if ($isMultiInstall): ?>
        <div class="iz-headsub"><b>複数組織（multi・上級者向け）</b>構成です。全体管理者（<b>superadmin</b>）アカウントを作成します。<b>組織の作成・管理は現在 API 経由</b>（管理画面は未提供）です。1 事業者の場合は戻って「単一組織」を推奨します。</div>
        <?php else: ?>
        <div class="iz-headsub">最初の管理者アカウントと、請求書に印字する会社情報を設定します。</div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
          <div class="alert error"><?= ico('warn') ?><div class="a-body"><div class="a-title">入力内容を確認してください</div><div class="a-text"><?= h(implode(' ', $errors)) ?></div></div></div>
        <?php endif; ?>

        <form method="post" action="install.php?step=2" id="adminForm">
          <?php if (!$isMultiInstall): ?>
          <div class="field">
            <label class="label" for="company_name">会社名（法人名）<span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body">適格請求書（インボイス）の<b>発行者名</b>として、見積書・請求書 PDF に印字されます。正式名称を入力してください。後から設定画面で変更できます。</span></span>
            </label>
            <input id="company_name" name="company_name" class="input<?= isset($fieldErrors['company']) ? ' is-error' : '' ?>" value="<?= $old('company_name') ?>" placeholder="例: 株式会社ねね商事" required>
            <?php if (isset($fieldErrors['company'])): ?><p class="err-text"><?= ico('warn') ?><?= h($fieldErrors['company']) ?></p><?php else: ?><p class="hint">請求書 PDF に発行者として印字されます（後から変更可）。</p><?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="field">
            <label class="label" for="admin_email"><?= $isMultiInstall ? '全体管理者メールアドレス' : '管理者メールアドレス' ?><span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body"><?= $isMultiInstall ? '全体管理者（superadmin）アカウントのログイン ID になります。組織に属さず、すべての組織を管理できます。' : '最初の管理者（admin）アカウントのログイン ID になります。運用担当者のメールを推奨します。' ?></span></span>
            </label>
            <input id="admin_email" name="admin_email" type="email" class="input<?= isset($fieldErrors['email']) ? ' is-error' : '' ?>" value="<?= $old('admin_email') ?>" placeholder="例: admin@yourcompany.co.jp" required>
            <?php if (isset($fieldErrors['email'])): ?><p class="err-text"><?= ico('warn') ?><?= h($fieldErrors['email']) ?></p><?php else: ?><p class="hint">このメールが<b>最初の管理者ログイン ID</b> になります。</p><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="admin_password">管理者パスワード<span class="opt">（12 文字以上）</span><span class="req">*</span>
              <span class="tip" tabindex="0">?<span class="tip-body">12 文字以上。パスワードは安全にハッシュ化して保存され、元の文字列は保持されません。<b>DB 接続パスワードとは別物</b>です。</span></span>
            </label>
            <div class="pw-wrap">
              <input id="admin_password" name="admin_password" class="input<?= isset($fieldErrors['pw']) ? ' is-error' : '' ?>" type="password" placeholder="12 文字以上" required minlength="12">
              <button type="button" class="pw-eye" data-pw="admin_password" tabindex="-1" aria-label="パスワード表示切替"><?= ico('eye') ?></button>
            </div>
            <?php if (isset($fieldErrors['pw'])): ?><p class="err-text"><?= ico('warn') ?><?= h($fieldErrors['pw']) ?></p><?php else: ?><p class="hint">12 文字以上。<b>ハッシュ化して安全に保管</b>されます（元の文字列は保存されません）。</p><?php endif; ?>
          </div>

          <div class="btn-row">
            <a class="btn btn-ghost btn-back" href="install.php?step=1" aria-label="戻る"><?= ico('back') ?></a>
            <button type="submit" class="btn btn-primary">インストールを実行<?= ico('arrow') ?></button>
          </div>
        </form>

        <?php else: /* complete */ ?>
        <div class="done-mark"><?= ico('check') ?></div>
        <div class="done-title">インストール完了</div>
        <div class="done-sub">NeNe Invoice のセットアップが完了しました。管理画面にログインして、最初の請求書を作成しましょう。</div>

        <div class="sec-warn">
          <span class="sw-ico"><?= ico('trash') ?></span>
          <div>
            <div class="sw-t">セキュリティ: 必ず install.php を削除してください</div>
            <div class="sw-d">放置すると第三者に再セットアップされる恐れがあります。FTP またはファイルマネージャから <code>install.php</code> を<b>削除（またはリネーム）</b>してください。</div>
          </div>
        </div>

        <div class="next-h">次のステップ</div>
        <ol class="next-list">
          <li><span class="nl-n">1</span><div><b><code>install.php</code> を削除する</b><div class="nl-d">最優先。サーバーからこのファイルを消します。</div></div></li>
          <li><span class="nl-n">2</span><div><b>管理画面にログイン</b><div class="nl-d">先ほど設定した管理者メール・パスワードで。</div></div></li>
          <li><span class="nl-n">3</span><div><b>会社情報・登録番号・銀行口座を設定</b><div class="nl-d">最初の見積書・請求書を作成できます。</div></div></li>
        </ol>

        <a class="btn btn-primary btn-block btn-lg" href="./"><?= ico('login') ?>管理画面にログイン</a>
        <?php endif; ?>
      </div>

      <!-- ローディング（フォーム送信中に JS で表示） -->
      <div class="iz-form iz-loading" id="izLoading" hidden>
        <div class="ld-h">インストールしています</div>
        <div class="ld-sub">接続の確認からテーブル作成までを順に実行しています。完了までこのページを開いたままにしてください。</div>
        <div class="ld-bar"><span id="ldBar"></span></div>
        <ul class="substeps" id="substeps">
          <li data-ss class="ss-pending"><span class="ss-ic"></span><div><div class="ss-t">接続を確認しています</div><div class="ss-d">データベースサーバーへ接続</div></div><span class="ss-meta">待機中</span></li>
          <li data-ss class="ss-pending"><span class="ss-ic"></span><div><div class="ss-t">テーブルを作成しています</div><div class="ss-d">スキーマを適用中</div></div><span class="ss-meta">待機中</span></li>
          <li data-ss class="ss-pending"><span class="ss-ic"></span><div><div class="ss-t">設定を保存しています</div><div class="ss-d">.env を書き出し中</div></div><span class="ss-meta">待機中</span></li>
        </ul>
        <div class="ld-warn"><?= ico('warn') ?>このページを閉じたり、ボタンを二度押ししないでください。</div>
      </div>
    </div>
  </div>
</div>

<script src="installer.js"></script>
</body>
</html>
