<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Sqlsrv;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class SqlsrvOne extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'sqlsrv_table_one';

    protected static function newFactory()
    {
        return SqlsrvOneFactory::new();
    }
}
