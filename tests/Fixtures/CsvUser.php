<?php

namespace LangleyFoxall\LaravelCsv\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class CsvUser extends Model
{
    protected $table = 'csv_users';

    protected $guarded = [];
}
