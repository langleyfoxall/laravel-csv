<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

interface WithHeadingFormatter
{
    public function formatHeading(?string $heading): string;
}
