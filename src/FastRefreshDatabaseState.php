<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases;

final class FastRefreshDatabaseState
{
    public static ?string $cachedChecksum = null;

    public static ?string $currentChecksum = null;
}
