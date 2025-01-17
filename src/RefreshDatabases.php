<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;
use Illuminate\Support\Facades\DB;

trait RefreshDatabases
{
    use CanConfigureMigrationCommands, RefreshDatabase;

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    public function setupRefreshDatabases()
    {
        $this->refreshTestDatabase();
    }

    /**
     * Begin a database transaction on the testing database.
     *
     * @return void
     */
    public function beginDatabaseTransactions()
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);

            if ($this->app->resolved('db.transactions')) {
                $this->app->make('db.transactions')->callbacksShouldIgnore(
                    $this->app->make('db.transactions')->getTransactions()->first()
                );
            }
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();
                $connection->rollBack();
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }

    /**
     * Refresh a conventional test database.
     *
     * @return void
     */
    protected function refreshTestDatabase()
    {
        if (!RefreshDatabaseState::$migrated) {
            foreach ($this->connectionsToTransact() as $connection) {
                $path = 'database/migrations/';

                $defaultConnection = config('database.default');

                $path .= $connection === $defaultConnection ? '' : $connection;

                $this->artisan('migrate:fresh', [
                    '--database' => $connection,
                    '--path' => $path,
                ]);

                if (str($connection)->contains('sqlsrv')) {
                    DB::connection($connection)
                        ->statement(
                            file_get_contents(
                                database_path('schema/' . $connection . '-schema.sql')
                            )
                        );

                    if (file_exists(database_path('schema/' . $connection . '-view.sql'))) {
                        $contents = file_get_contents(database_path('schema/' . $connection . '-view.sql'));

                        $contents = explode('--', $contents);

                        foreach ($contents as $content) {
                            DB::connection($connection)
                                ->statement(trim($content));
                        }
                    }
                }

                if (file_exists(database_path('schema/' . $connection . '-seed.sql'))) {
                    $contents = file_get_contents(database_path('schema/' . $connection . '-seed.sql'));

                    $contents = explode('--', $contents);

                    foreach ($contents as $content) {
                        DB::connection($connection)
                            ->statement(trim($content));
                    }
                }
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransactions();
    }

    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connectionsToTransact()
    {
        return explode(',', env('DB_CONNECTIONS'));
    }
}
