<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

use Illuminate\Support\Collection;

interface OnEachRow
{
    public function onRow(Collection $row, int $rowNumber): void;
}
