<?php

declare(strict_types=1);

use Illuminate\Support\Env;

$basePath = dirname(__DIR__);

$sqlitePath = (string) Env::get('DB_DATABASE', 'database/database.sqlite');
if ($sqlitePath !== ':memory:' && !str_starts_with($sqlitePath, '/')) {
    $sqlitePath = $basePath . '/' . $sqlitePath;
}

return [
    'default' => Env::get('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::get('DB_PORT', '3306'),
            'database' => Env::get('DB_DATABASE', 'app'),
            'username' => Env::get('DB_USERNAME', 'root'),
            'password' => Env::get('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'path' => $basePath . '/database/migrations',
    ],
];
