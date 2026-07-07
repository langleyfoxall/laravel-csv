<?php

namespace LangleyFoxall\LaravelCsv\Concerns;

use LangleyFoxall\LaravelCsv\Enums\ChunkOrder;

interface WithChunkOrder
{
    public function chunkOrder(): ChunkOrder;
}
