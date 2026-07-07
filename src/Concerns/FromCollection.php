<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

use Illuminate\Support\Collection;

interface FromCollection
{
    public function collection(): Collection;
}
