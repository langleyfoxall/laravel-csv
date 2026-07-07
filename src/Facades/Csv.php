<?php

namespace LangleyFoxall\LaravelCsv\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LangleyFoxall\LaravelCsv\Csv
 */
class Csv extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LangleyFoxall\LaravelCsv\Csv::class;
    }
}
