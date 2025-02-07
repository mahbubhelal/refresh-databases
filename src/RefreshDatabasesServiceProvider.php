<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Support\ServiceProvider;
use Mahbub\RefreshDatabases\Command\RemoveChecksumCommand;

class RefreshDatabasesServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register() {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RemoveChecksumCommand::class,
            ]);
        }
    }
}
