<?php

namespace LangleyFoxall\LaravelCsv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LangleyFoxall\LaravelCsv\Concerns\FromArray;
use LangleyFoxall\LaravelCsv\Concerns\FromCollection;
use LangleyFoxall\LaravelCsv\Concerns\FromIterable;
use LangleyFoxall\LaravelCsv\Concerns\FromQuery;
use LangleyFoxall\LaravelCsv\Concerns\OnEachRow;
use LangleyFoxall\LaravelCsv\Concerns\PreservesEmptyRows;
use LangleyFoxall\LaravelCsv\Concerns\ToCollection;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkColumn;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkOrder;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkReading;
use LangleyFoxall\LaravelCsv\Concerns\WithCustomCsvSettings;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingFormatter;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingRow;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadings;
use LangleyFoxall\LaravelCsv\Concerns\WithMapping;
use LangleyFoxall\LaravelCsv\Concerns\WithStrictColumnCount;
use LangleyFoxall\LaravelCsv\Enums\ChunkOrder;
use LangleyFoxall\LaravelCsv\Exceptions\ColumnCountMismatch;
use LangleyFoxall\LaravelCsv\Exceptions\DuplicateHeading;
use LangleyFoxall\LaravelCsv\Exceptions\InvalidExport;
use LangleyFoxall\LaravelCsv\Exceptions\InvalidImport;
use LangleyFoxall\LaravelCsv\Exceptions\UnableToReadFile;
use LangleyFoxall\LaravelCsv\Exceptions\UnableToWriteFile;
use LangleyFoxall\LaravelCsv\Support\StoredCsv;
use LangleyFoxall\LaravelCsv\Support\TemporaryCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    public function raw(object $export): string
    {
        $stream = fopen('php://temp', 'r+');

        $this->write($stream, $export);

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    public function download(object $export, string $fileName, array $headers = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $stream = fopen('php://output', 'w');
            $this->write($stream, $export);
            fclose($stream);
        }, $fileName, array_replace([
            'Content-Type' => 'text/csv; charset=UTF-8',
        ], $headers));
    }

    public function store(object $export, string $path, ?string $disk = null, array $options = []): StoredCsv
    {
        $stream = fopen('php://temp', 'r+');

        $this->write($stream, $export);

        rewind($stream);
        $stored = Storage::disk($disk)->put($path, $stream, $options);
        fclose($stream);

        if (! $stored) {
            throw UnableToWriteFile::fromDisk($path, $disk);
        }

        return new StoredCsv($path, $disk, true);
    }

    public function temporaryFile(object $export, ?string $fileName = null): TemporaryCsv
    {
        $fileName ??= Str::uuid().'.csv';
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$fileName;
        $stream = fopen($path, 'w+');

        $this->write($stream, $export);
        fclose($stream);

        return new TemporaryCsv($path, $fileName);
    }

    public function import(object $import, string $path, ?string $disk = null): void
    {
        if (! $import instanceof ToCollection && ! $import instanceof OnEachRow) {
            throw InvalidImport::unsupported($import);
        }

        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw UnableToReadFile::fromDisk($path, $disk);
        }

        try {
            $this->read($stream, $import);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function write($stream, object $export): void
    {
        $settings = $this->settings($export);
        $expectedColumns = null;
        $rowNumber = 0;

        if ($settings['use_bom']) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        if ($export instanceof WithHeadings) {
            $headings = $this->flattenRow($export->headings());
            $expectedColumns = count($headings);
            $this->writeRow($stream, $headings, $settings);
        }

        foreach ($this->rowsForExport($export) as $row) {
            $rowNumber++;
            $row = $this->rowForExport($export, $row);

            if ($export instanceof WithStrictColumnCount && $expectedColumns !== null && count($row) !== $expectedColumns) {
                throw ColumnCountMismatch::forRow($rowNumber, $expectedColumns, count($row));
            }

            $this->writeRow($stream, $row, $settings);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writeRow($stream, array $row, array $settings): void
    {
        fputcsv(
            $stream,
            $row,
            $settings['delimiter'],
            $settings['enclosure'],
            $settings['escape_character'],
            $settings['line_ending'],
        );
    }

    private function rowsForExport(object $export): iterable
    {
        if ($export instanceof FromQuery) {
            $chunkSize = $export instanceof WithChunkReading ? $export->chunkSize() : config('csv.chunk_size');
            $column = $export instanceof WithChunkColumn ? $export->chunkColumn() : config('csv.chunk_column');
            $alias = $export instanceof WithChunkColumn ? $export->chunkAlias() : config('csv.chunk_alias');
            $descending = $export instanceof WithChunkOrder && $export->chunkOrder() === ChunkOrder::Desc;

            if ($descending) {
                return $export->query()->lazyByIdDesc($chunkSize, $column, $alias);
            }

            return $export->query()->lazyById($chunkSize, $column, $alias);
        }

        if ($export instanceof FromIterable) {
            return $export->iterable();
        }

        if ($export instanceof FromCollection) {
            return $export->collection();
        }

        if ($export instanceof FromArray) {
            return $export->array();
        }

        throw InvalidExport::unsupported($export);
    }

    private function rowForExport(object $export, mixed $row): array
    {
        if ($export instanceof WithMapping) {
            return $this->flattenRow($export->map($row));
        }

        return $this->flattenRow($row);
    }

    private function flattenRow(mixed $row): array
    {
        if ($row instanceof Model) {
            $row = $row->toArray();
        } elseif ($row instanceof Collection) {
            $row = $row->all();
        } elseif (is_object($row)) {
            $row = get_object_vars($row);
        }

        if (! is_array($row)) {
            return [$row];
        }

        return array_values($row);
    }

    /**
     * @param  resource  $stream
     */
    private function read($stream, object $import): void
    {
        $settings = $this->settings($import);
        $usesHeadingRow = $import instanceof WithHeadingRow;
        $headings = null;
        $rows = collect();
        $chunk = collect();
        $rowNumber = 0;
        $chunkSize = $import instanceof WithChunkReading ? $import->chunkSize() : null;

        while (($row = fgetcsv($stream, null, $settings['delimiter'], $settings['enclosure'], $settings['escape_character'])) !== false) {
            $rowNumber++;
            $row = $this->stripBomFromFirstValue($row);

            if ($usesHeadingRow && $headings === null) {
                $headings = $this->headings($row, $import);

                continue;
            }

            if (! $import instanceof PreservesEmptyRows && $this->isEmptyRow($row)) {
                continue;
            }

            $preparedRow = collect($row);

            if ($usesHeadingRow) {
                $preparedRow = collect($this->combineHeadings($headings, $row));
            }

            if ($import instanceof OnEachRow) {
                $import->onRow($preparedRow, $rowNumber);

                continue;
            }

            if (! $import instanceof ToCollection) {
                continue;
            }

            if ($chunkSize !== null) {
                $chunk->push($preparedRow);

                if ($chunk->count() >= $chunkSize) {
                    $import->collection($chunk);
                    $chunk = collect();
                }

                continue;
            }

            $rows->push($preparedRow);
        }

        if ($import instanceof ToCollection) {
            if ($chunkSize !== null) {
                if ($chunk->isNotEmpty()) {
                    $import->collection($chunk);
                }

                return;
            }

            $import->collection($rows);
        }
    }

    private function headings(array $row, object $import): array
    {
        $headings = array_map(fn (?string $heading): string => $this->normalizeHeading($heading, $import), $row);
        $counts = array_count_values($headings);

        foreach ($counts as $heading => $count) {
            if ($count > 1) {
                throw DuplicateHeading::forHeading($heading);
            }
        }

        return $headings;
    }

    private function combineHeadings(array $headings, array $row): array
    {
        return array_combine($headings, array_slice(array_pad($row, count($headings), null), 0, count($headings))) ?: [];
    }

    private function stripBomFromFirstValue(array $row): array
    {
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
        }

        return $row;
    }

    private function normalizeHeading(?string $heading, object $import): string
    {
        if ($import instanceof WithHeadingFormatter) {
            return $import->formatHeading($heading);
        }

        return Str::slug((string) $heading, '_');
    }

    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => $value === null || $value === '');
    }

    private function settings(object $concern): array
    {
        $defaults = config('csv');
        $settings = $concern instanceof WithCustomCsvSettings
            ? array_replace($defaults, $concern->getCsvSettings())
            : $defaults;

        return [
            'delimiter' => $settings['delimiter'],
            'enclosure' => $settings['enclosure'],
            'escape_character' => $settings['escape_character'],
            'line_ending' => $settings['line_ending'],
            'use_bom' => $settings['use_bom'],
        ];
    }
}
