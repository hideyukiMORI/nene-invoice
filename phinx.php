<?php

declare(strict_types=1);

use Nene2\Config\ConfigLoader;

require_once __DIR__ . '/vendor/autoload.php';

$database = (new ConfigLoader(__DIR__))->load()->database;

return [
    'paths' => [
        'migrations' => 'database/migrations',
        'seeds' => 'database/seeds',
    ],
    'environments' => [
        'default_environment' => $database->environment,
        $database->environment => $database->usesUrl()
            ? ['url' => $database->url]
            : [
                'adapter' => $database->adapter,
                'host' => $database->host,
                'name' => $database->name,
                'user' => $database->user,
                'pass' => $database->password,
                'port' => $database->port,
                'charset' => $database->charset,
                // SQLite only: use DB_NAME verbatim as the file path so Phinx and
                // the application connect to the same file (Phinx otherwise appends
                // a `.sqlite3` suffix). Ignored by the MySQL / PostgreSQL adapters.
                'suffix' => '',
            ],
    ],
    'version_order' => 'creation',
];
