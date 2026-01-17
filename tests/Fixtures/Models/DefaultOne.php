<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class DefaultOne extends Model
{
    use HasFactory;

    protected $connection = 'default';

    protected $table = 'default_table_one';

    protected static function newFactory()
    {
        return DefaultOneFactory::new();
    }
}
