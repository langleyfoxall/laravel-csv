<?php

namespace LangleyFoxall\LaravelCsv\Exceptions;

class UnableToWriteFile extends CsvException
{
    public static function fromDisk(string $path, ?string $disk): self
    {
        $diskName = $disk ?? 'default';

        return new self("Unable to write CSV file [{$path}] to disk [{$diskName}].");
    }
}
