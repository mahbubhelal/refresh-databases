<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Support\ServiceProvider;
use Mahbub\RefreshDatabases\Command\RemoveChecksumCommand;

final class RefreshDatabasesServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RemoveChecksumCommand::class,
            ]);
        }
    }
}
