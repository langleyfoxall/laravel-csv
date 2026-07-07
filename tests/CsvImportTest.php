<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use LangleyFoxall\LaravelCsv\Concerns\OnEachRow;
use LangleyFoxall\LaravelCsv\Concerns\PreservesEmptyRows;
use LangleyFoxall\LaravelCsv\Concerns\ToCollection;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkReading;
use LangleyFoxall\LaravelCsv\Concerns\WithCustomCsvSettings;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingFormatter;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingRow;
use LangleyFoxall\LaravelCsv\Exceptions\DuplicateHeading;
use LangleyFoxall\LaravelCsv\Facades\Csv;

it('imports rows into a collection', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "Jane,jane@example.com\nJohn,john@example.com\n");

    $import = new class implements ToCollection
    {
        public Collection $rows;

        public function collection(Collection $rows): void
        {
            $this->rows = $rows;
        }
    };

    Csv::import($import, 'imports/users.csv', 'local');

    expect($import->rows)->toHaveCount(2)
        ->and($import->rows->first()->all())->toBe(['Jane', 'jane@example.com']);
});

it('imports heading rows with normalized keys', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "\xEF\xBB\xBFFirst Name,Email Address\nJane,jane@example.com\n");

    $import = new class implements ToCollection, WithHeadingRow
    {
        public Collection $rows;

        public function collection(Collection $rows): void
        {
            $this->rows = $rows;
        }
    };

    Csv::import($import, 'imports/users.csv', 'local');

    expect($import->rows->first()->all())->toBe([
        'first_name' => 'Jane',
        'email_address' => 'jane@example.com',
    ]);
});

it('throws for duplicate headings', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "Email,Email\njane@example.com,other@example.com\n");

    $import = new class implements ToCollection, WithHeadingRow
    {
        public function collection(Collection $rows): void {}
    };

    Csv::import($import, 'imports/users.csv', 'local');
})->throws(DuplicateHeading::class);

it('imports rows one at a time with original csv row numbers', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "Name\nJane\n\nJohn\n");

    $import = new class implements OnEachRow, WithHeadingRow
    {
        public array $rows = [];

        public function onRow(Collection $row, int $rowNumber): void
        {
            $this->rows[] = [$row->all(), $rowNumber];
        }
    };

    Csv::import($import, 'imports/users.csv', 'local');

    expect($import->rows)->toBe([
        [['name' => 'Jane'], 2],
        [['name' => 'John'], 4],
    ]);
});

it('imports collections in chunks', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "Jane\nJohn\nJen\n");

    $import = new class implements ToCollection, WithChunkReading
    {
        public array $chunks = [];

        public function chunkSize(): int
        {
            return 2;
        }

        public function collection(Collection $rows): void
        {
            $this->chunks[] = $rows->map->all()->all();
        }
    };

    Csv::import($import, 'imports/users.csv', 'local');

    expect($import->chunks)->toBe([
        [['Jane'], ['John']],
        [['Jen']],
    ]);
});

it('preserves empty rows when requested', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "Jane\n\nJohn\n");

    $import = new class implements PreservesEmptyRows, ToCollection
    {
        public Collection $rows;

        public function collection(Collection $rows): void
        {
            $this->rows = $rows;
        }
    };

    Csv::import($import, 'imports/users.csv', 'local');

    expect($import->rows->map->all()->all())->toBe([
        ['Jane'],
        [null],
        ['John'],
    ]);
});

it('supports custom import csv settings and heading formatter', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/users.csv', "First Name;Email\nJane;jane@example.com\n");

    $import = new class implements ToCollection, WithCustomCsvSettings, WithHeadingFormatter, WithHeadingRow
    {
        public Collection $rows;

        public function getCsvSettings(): array
        {
            return ['delimiter' => ';'];
        }

        public function formatHeading(?string $heading): string
        {
            return strtoupper(str_replace(' ', '_', (string) $heading));
        }

        public function collection(Collection $rows): void
        {
            $this->rows = $rows;
        }
    };

    Csv::import($import, 'imports/users.csv', 'local');

    expect($import->rows->first()->all())->toBe([
        'FIRST_NAME' => 'Jane',
        'EMAIL' => 'jane@example.com',
    ]);
});
