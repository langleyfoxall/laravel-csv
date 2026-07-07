<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

interface WithChunkColumn
{
    public function chunkColumn(): string;

    public function chunkAlias(): ?string;
}
