<?php

namespace LangleyFoxall\LaravelCsv\Support;

use Illuminate\Support\Facades\Storage;

readonly class StoredCsv
{
    public function __construct(
        private string $path,
        private ?string $disk,
        private bool $stored,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function disk(): ?string
    {
        return $this->disk;
    }

    public function stored(): bool
    {
        return $this->stored;
    }

    public function temporaryUrl(\DateTimeInterface $expiration, array $options = []): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, $expiration, $options);
    }
}
