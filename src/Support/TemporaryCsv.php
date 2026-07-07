<?php

namespace LangleyFoxall\LaravelCsv\Support;

readonly class TemporaryCsv
{
    public function __construct(
        private string $path,
        private string $fileName,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }
}
