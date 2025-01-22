<?php

declare(strict_types=1);

namespace Mahbub\FastRefreshDatabases;

use Illuminate\Support\ServiceProvider;
use Mahbub\FastRefreshDatabases\Command\RemoveChecksum;

class FastRefreshDatabasesServiceProvider extends ServiceProvider
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
