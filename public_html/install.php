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
 */

define('ROOT', dirname(__DIR__));
define('INSTALLED_MARKER', ROOT . '/var/.installed');
define('MYSQL_SCHEMA', ROOT . '/database/schema/mysql/schema.sql');
define('ENV_FILE', ROOT . '/.env');

// -------------------------------------------------------------------
// Guard: すでにインストール済みならブロック
// -------------------------------------------------------------------
if (file_exists(INSTALLED_MARKER)) {
    http_response_code(403);
    echo '<p style="font-family:sans-serif;color:#c00;padding:2em">インストール済みです。install.php を削除してください。</p>';
    exit;
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
    http_response_code(403);
    echo '<p style="font-family:sans-serif;color:#c00;padding:2em">既にプロビジョニング済みのデータベースが検出されました。再インストールはできません。install.php を削除してください。</p>';
    exit;
}

// -------------------------------------------------------------------
// ヘルパー
// -------------------------------------------------------------------
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function checkRequirements(): array
{
    $errors = [];

    if (PHP_VERSION_ID < 80400) {
        $errors[] = 'PHP 8.4 以上が必要です（現在: ' . PHP_VERSION . '）';
    }

    foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json'] as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "PHP 拡張モジュール {$ext} が見つかりません";
        }
    }

    if (!is_writable(ROOT . '/var') && !mkdir(ROOT . '/var', 0755, true) && !is_dir(ROOT . '/var')) {
        $errors[] = 'var/ ディレクトリへの書き込み権限がありません';
    }

    if (!is_writable(ROOT)) {
        $errors[] = 'ルートディレクトリへの書き込み権限がありません（.env ファイルを作成できません）';
    }

    if (!file_exists(ROOT . '/vendor/autoload.php')) {
        $errors[] = 'vendor/ ディレクトリが見つかりません。ZIP ファイルが完全に展開されているか確認してください';
    }

    return $errors;
}

function applySchema(PDO $pdo, string $schemaFile): void
{
    $sql = file_get_contents($schemaFile);

    if ($sql === false) {
        throw new RuntimeException('スキーマファイルを読み込めませんでした: ' . $schemaFile);
    }

    // 空行・コメント行を除いてセミコロン区切りで分割
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        static fn (string $s): bool => $s !== '' && !str_starts_with(ltrim($s), '--'),
    );

    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
}

function writeEnv(string $dbHost, int $dbPort, string $dbName, string $dbUser, string $dbPass): void
{
    $secret = bin2hex(random_bytes(32));

    $content = <<<ENV
APP_ENV=production
APP_DEBUG=false
APP_NAME="NeNe Invoice"
PROBLEM_DETAILS_BASE_URL=https://nene-invoice.dev/problems/

# --- Tenancy ---
TENANT_RESOLUTION=single
ORG_SLUG=
BASE_DOMAIN=

# --- Auth ---
NENE2_LOCAL_JWT_SECRET={$secret}

# --- Database ---
DB_ADAPTER=mysql
DB_NAME={$dbName}
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_USER={$dbUser}
DB_PASSWORD={$dbPass}
DB_CHARSET=utf8mb4
ENV;

    file_put_contents(ENV_FILE, $content);
}

// -------------------------------------------------------------------
// POST 処理
// -------------------------------------------------------------------
$step        = (int) ($_GET['step'] ?? 1);
$errors      = [];
$success     = false;
$reqErrors   = checkRequirements();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // DB 接続テスト
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = (int) ($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';

        if ($dbName === '' || $dbUser === '') {
            $errors[] = 'DB 名とユーザー名は必須です';
        } else {
            try {
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                applySchema($pdo, MYSQL_SCHEMA);
                writeEnv($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

                header('Location: install.php?step=2');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'DB 接続エラー: ' . $e->getMessage();
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($step === 2) {
        // 管理者ユーザー作成
        $adminEmail    = trim($_POST['admin_email'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $companyName   = trim($_POST['company_name'] ?? '');

        if ($adminEmail === '' || $adminPassword === '' || $companyName === '') {
            $errors[] = 'すべての項目を入力してください';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください';
        } elseif (strlen($adminPassword) < 8) {
            $errors[] = 'パスワードは 8 文字以上で設定してください';
        } elseif (!file_exists(ENV_FILE)) {
            $errors[] = '.env ファイルが見つかりません。ステップ 1 からやり直してください';
        } else {
            try {
                // .env から接続情報を再読込
                $env = parse_ini_file(ENV_FILE);

                if ($env === false) {
                    throw new RuntimeException('.env ファイルを読み込めませんでした');
                }

                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $env['DB_HOST'] ?? 'localhost',
                    $env['DB_PORT'] ?? '3306',
                    $env['DB_NAME'] ?? '',
                );
                $pdo = new PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASSWORD'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                $now = date('Y-m-d H:i:s');

                // 組織を作成
                $orgSlug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($companyName)) ?: 'default';
                $pdo->prepare('INSERT INTO organizations (name, slug, plan, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)')
                    ->execute([$companyName, $orgSlug, 'free', $now, $now]);
                $orgId = (int) $pdo->lastInsertId();

                // 会社設定を作成
                $pdo->prepare('INSERT INTO company_settings (organization_id, legal_name, created_at, updated_at) VALUES (?, ?, ?, ?)')
                    ->execute([$orgId, $companyName, $now, $now]);

                // 管理者ユーザーを作成
                $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
                $pdo->prepare('INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$adminEmail, $hash, 'admin', $orgId, 'active', $now, $now]);

                // .installed マーカーを作成
                file_put_contents(INSTALLED_MARKER, date('c'));

                $success = true;
            } catch (PDOException $e) {
                $errors[] = 'データベースエラー: ' . $e->getMessage();
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NeNe Invoice インストーラー</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, 'Hiragino Kaku Gothic Pro', sans-serif; background: #f5f5f5; color: #333; padding: 2rem 1rem; }
.container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 2rem; }
h1 { font-size: 1.4rem; margin-bottom: .25rem; }
.subtitle { color: #666; font-size: .9rem; margin-bottom: 1.5rem; }
.steps { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
.step { flex: 1; padding: .4rem; text-align: center; border-radius: 4px; font-size: .8rem; background: #e8e8e8; color: #888; }
.step.active { background: #0066cc; color: #fff; }
.step.done { background: #28a745; color: #fff; }
label { display: block; font-size: .85rem; color: #555; margin-bottom: .25rem; margin-top: 1rem; }
input[type=text], input[type=email], input[type=password], input[type=number] {
    width: 100%; padding: .5rem .75rem; border: 1px solid #ccc; border-radius: 4px; font-size: .95rem;
}
button { margin-top: 1.5rem; width: 100%; padding: .65rem; background: #0066cc; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
button:hover { background: #0052a3; }
.error { background: #fff0f0; border: 1px solid #f5c6c6; border-radius: 4px; padding: .75rem 1rem; margin-bottom: 1rem; color: #c00; font-size: .9rem; }
.error li { margin-left: 1.2rem; margin-top: .25rem; }
.success { background: #f0fff0; border: 1px solid #b2dfb2; border-radius: 4px; padding: 1rem; color: #2a7a2a; }
.success h2 { margin-bottom: .5rem; }
.success a { color: #0066cc; }
.req-error { color: #c00; font-size: .85rem; }
.check-ok { color: #2a7a2a; font-size: .85rem; }
hr { border: none; border-top: 1px solid #eee; margin: 1.25rem 0; }
</style>
</head>
<body>
<div class="container">
  <h1>NeNe Invoice</h1>
  <p class="subtitle">セットアップウィザード</p>

  <?php if ($reqErrors !== []) : ?>
  <div class="error">
    <strong>要件チェック失敗</strong>
    <ul>
      <?php foreach ($reqErrors as $e) : ?>
      <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php return; endif; ?>

  <div class="steps">
    <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1. データベース</div>
    <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. 管理者設定</div>
    <div class="step <?= $success ? 'done' : '' ?>">3. 完了</div>
  </div>

  <?php if ($errors !== []) : ?>
  <div class="error">
    <ul>
      <?php foreach ($errors as $e) : ?>
      <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($success) : ?>
  <div class="success">
    <h2>✓ インストール完了</h2>
    <p>管理画面にログインしてください。</p>
    <p style="margin-top:.75rem"><a href="/admin/">管理画面を開く →</a></p>
    <hr>
    <p style="font-size:.85rem;color:#555">
      <strong>セキュリティ:</strong> install.php を削除または .htaccess でアクセス禁止にしてください。
    </p>
  </div>

  <?php elseif ($step === 1) : ?>
  <form method="post" action="install.php?step=1">
    <p style="font-size:.9rem;color:#555">MySQL の接続情報を入力してください。</p>

    <label for="db_host">ホスト</label>
    <input type="text" id="db_host" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required>

    <label for="db_port">ポート</label>
    <input type="number" id="db_port" name="db_port" value="<?= h((string) ($_POST['db_port'] ?? 3306)) ?>" required>

    <label for="db_name">データベース名</label>
    <input type="text" id="db_name" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required>

    <label for="db_user">ユーザー名</label>
    <input type="text" id="db_user" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required>

    <label for="db_pass">パスワード</label>
    <input type="password" id="db_pass" name="db_pass">

    <button type="submit">接続テスト &amp; スキーマ適用 →</button>
  </form>

  <?php else : ?>
  <form method="post" action="install.php?step=2">
    <p style="font-size:.9rem;color:#555">管理者アカウントと会社情報を設定します。</p>

    <label for="company_name">会社名（法人名）</label>
    <input type="text" id="company_name" name="company_name" value="<?= h($_POST['company_name'] ?? '') ?>" required>

    <label for="admin_email">管理者メールアドレス</label>
    <input type="email" id="admin_email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required>

    <label for="admin_password">管理者パスワード（8 文字以上）</label>
    <input type="password" id="admin_password" name="admin_password" required minlength="8">

    <button type="submit">インストール実行 →</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
