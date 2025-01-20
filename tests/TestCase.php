<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases\Tests;

use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Tcb\FastRefreshDatabases\FastRefreshDatabasesServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    protected $connectionsToTransact = [
        __DIR__ . '/Fixtures/migrations' => 'default',
        __DIR__ . '/Fixtures/migrations/other' => 'other',
    ];

    #[\Override]
    protected function getPackageProviders($app)
    {
        return [
            FastRefreshDatabasesServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $baseOptions = [
            'url' => '',
            'host' => '127.0.0.1',
            'port' => '13306',
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
    }
}
