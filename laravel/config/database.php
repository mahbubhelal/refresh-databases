<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'cid' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('TCB_CID_HOST', '127.0.0.1'),
            'port' => env('TCB_CID_PORT', '3306'),
            'database' => env('TCB_CID_DATABASE', 'laravel'),
            'username' => env('TCB_CID_USERNAME', 'root'),
            'password' => env('TCB_CID_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
