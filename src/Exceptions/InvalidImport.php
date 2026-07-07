<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class InvalidImport extends CsvException
{
    public static function unsupported(object $import): self
    {
        return new self('CSV import ['.$import::class.'] must implement ToCollection or OnEachRow.');
    }
}
