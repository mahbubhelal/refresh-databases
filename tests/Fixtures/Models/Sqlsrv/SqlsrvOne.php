<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Sqlsrv;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Connection('sqlsrv')]
#[Table(name: 'sqlsrv_table_one')]
final class SqlsrvOne extends Model
{
    /** @use HasFactory<SqlsrvOneFactory> */
    use HasFactory;

    protected static function newFactory(): SqlsrvOneFactory
    {
        return SqlsrvOneFactory::new();
    }
}
