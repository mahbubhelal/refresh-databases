<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases;

class FastRefreshDatabaseState
{
    /** The checksum cached in the migrationChecksum.txt file */
    public static ?string $cachedChecksum = null;

    /** The current checksum calculated by the application */
    public static ?string $currentChecksum = null;
}
