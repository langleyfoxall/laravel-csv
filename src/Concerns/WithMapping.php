<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

interface WithMapping
{
    public function map(mixed $row): array;
}
