<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class OtherOne extends Model
{
    use HasFactory;

    protected $connection = 'other';

    protected $table = 'other_table_one';

    protected static function newFactory()
    {
        return OtherOneFactory::new();
    }
}
