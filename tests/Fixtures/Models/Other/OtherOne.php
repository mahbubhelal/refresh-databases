<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models\Other;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Connection('other')]
#[Table(name: 'other_table_one')]
final class OtherOne extends Model
{
    /** @use HasFactory<OtherOneFactory> */
    use HasFactory;

    protected static function newFactory(): OtherOneFactory
    {
        return OtherOneFactory::new();
    }
}
