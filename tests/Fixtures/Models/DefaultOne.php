<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultOne extends Model
{
    use HasFactory;

    protected $connection = 'default';

    protected $table = 'default_table_one';

    protected static function newFactory()
    {
        return DefaultOneFactory::new();
    }
}
