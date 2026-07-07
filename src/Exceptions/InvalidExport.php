<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class InvalidExport extends CsvException
{
    public static function unsupported(object $export): self
    {
        return new self('CSV export ['.$export::class.'] must implement FromArray, FromCollection, FromIterable, or FromQuery.');
    }
}
