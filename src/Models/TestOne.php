<?php

namespace Tcb\FastRefreshDatabases\Models;

use Illuminate\Database\Eloquent\Model;

class TestOne extends Model
{
    protected $connection = 'test_one';

    protected $table = 'table_one';
}
