<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

interface WithChunkReading
{
    public function chunkSize(): int;
}
