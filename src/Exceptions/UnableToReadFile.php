<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class UnableToReadFile extends CsvException
{
    public static function fromDisk(string $path, ?string $disk): self
    {
        return new self("Unable to read CSV file [{$path}] from disk [".($disk ?? 'default').'].');
    }
}
