<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

use Illuminate\Support\Collection;

interface ToCollection
{
    public function collection(Collection $rows): void;
}
