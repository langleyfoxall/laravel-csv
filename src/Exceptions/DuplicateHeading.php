<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class DuplicateHeading extends CsvException
{
    public static function forHeading(string $heading): self
    {
        return new self("CSV heading [{$heading}] appears more than once.");
    }
}
