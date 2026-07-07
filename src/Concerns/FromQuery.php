<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

interface FromQuery
{
    public function query(): EloquentBuilder|QueryBuilder;
}
