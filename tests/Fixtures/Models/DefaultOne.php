<?php

declare(strict_types=1);

namespace Mahbub\RefreshDatabases\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Connection('default')]
#[Table(name: 'default_table_one')]
final class DefaultOne extends Model
{
    /** @use HasFactory<DefaultOneFactory> */
    use HasFactory;

    protected static function newFactory(): DefaultOneFactory
    {
        return DefaultOneFactory::new();
    }
}
