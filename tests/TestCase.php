<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests;

use Mahbub\RefreshDatabases\RefreshDatabasesServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    /** @var array<string> */
    protected $connectionsToTransact = ['default', 'other'];

    #[\Override]
    protected function getPackageProviders($app)
    {
        return [
            RefreshDatabasesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $baseOptions = [
            'url' => '',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
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
        $app['config']->set('database.connections.default', $baseOptions);
        $app['config']->set('database.connections.other', array_merge($baseOptions, ['database' => 'other']));

        $app->useDatabasePath(__DIR__ . '/Fixtures/database');
        $app->useStoragePath(__DIR__ . '/Fixtures/storage');
    }
}
