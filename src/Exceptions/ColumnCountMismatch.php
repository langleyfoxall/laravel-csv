<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class ColumnCountMismatch extends CsvException
{
    public static function forRow(int $rowNumber, int $expected, int $actual): self
    {
        return new self("CSV row [{$rowNumber}] has [{$actual}] columns; expected [{$expected}].");
    }
}
