<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

use Illuminate\Support\ServiceProvider;
use Mahbub\RefreshDatabases\Command\RemoveChecksum;

class RefreshDatabasesServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register() {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RemoveChecksum::class,
            ]);
        }
    }
}
