<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class UnableToWriteFile extends CsvException
{
    public static function fromDisk(string $path, ?string $disk): self
    {
        return new self("Unable to write CSV file [{$path}] to disk [".($disk ?? 'default').'].');
    }
}
