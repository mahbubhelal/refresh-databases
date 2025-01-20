<?php

declare(strict_types=1);

namespace Tcb\FastRefreshDatabases\Tests\Fixtures\Models\Other;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherOne extends Model
{
    use HasFactory;

    protected $connection = 'other';

    protected $table = 'other_table_one';

    protected static function newFactory()
    {
        return OtherOneFactory::new();
    }
}
