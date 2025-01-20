<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases;

use Illuminate\Support\ServiceProvider;
use Tcb\FastRefreshDatabases\Command\RemoveChecksum;

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
