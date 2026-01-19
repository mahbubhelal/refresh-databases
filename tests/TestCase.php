<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests;

use Mahbub\RefreshDatabases\RefreshDatabasesServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    protected $connectionsToTransact = ['default', 'other', 'sqlsrv'];

    protected $migrationPaths = [
        'sqlsrv' => __DIR__ . '/Fixtures/database/migrations/sqlsrv',
    ];

    #[\Override]
    protected function getPackageProviders($app)
    {
        return [
            RefreshDatabasesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $mysqlOptions = [
            'url' => '',
            'host' => env('DB_HOST', 'test-mysql'),
            'port' => env('DB_PORT', '3306'),
            'driver' => 'mysql',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ];

        $app['config']->set('database.default', 'default');
        $app['config']->set('database.connections.default', $mysqlOptions);
        $app['config']->set('database.connections.other', array_merge($mysqlOptions, ['database' => 'other']));

        $app['config']->set('database.connections.sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => env('SQLSRV_HOST', 'test-sqlsrv'),
            'port' => env('SQLSRV_PORT', '1433'),
            'database' => env('SQLSRV_DATABASE', 'master'),
            'username' => env('SQLSRV_USERNAME', 'sa'),
            'password' => env('SQLSRV_PASSWORD', 'YourStrong@Passw0rd'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('SQLSRV_ENCRYPT', 'no'),
            'trust_server_certificate' => env('SQLSRV_TRUST_CERT', true),
        ]);

        $app->useDatabasePath(__DIR__ . '/Fixtures/database');
        $app->useStoragePath(__DIR__ . '/Fixtures/storage');
    }
}
