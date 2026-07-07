<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use LangleyFoxall\LaravelCsv\Concerns\FromArray;
use LangleyFoxall\LaravelCsv\Concerns\FromCollection;
use LangleyFoxall\LaravelCsv\Concerns\FromIterable;
use LangleyFoxall\LaravelCsv\Concerns\FromQuery;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkColumn;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkOrder;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkReading;
use LangleyFoxall\LaravelCsv\Concerns\WithCustomCsvSettings;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadings;
use LangleyFoxall\LaravelCsv\Concerns\WithMapping;
use LangleyFoxall\LaravelCsv\Concerns\WithStrictColumnCount;
use LangleyFoxall\LaravelCsv\Enums\ChunkOrder;
use LangleyFoxall\LaravelCsv\Exceptions\ColumnCountMismatch;
use LangleyFoxall\LaravelCsv\Facades\Csv;
use LangleyFoxall\LaravelCsv\Support\StoredCsv;
use LangleyFoxall\LaravelCsv\Tests\Fixtures\CsvUser;

it('exports raw csv from arrays with headings', function () {
    $export = new class implements FromArray, WithHeadings
    {
        public function headings(): array
        {
            return ['Name', 'Email'];
        }

        public function array(): array
        {
            return [
                ['Jane', 'jane@example.com'],
                ['John', 'john@example.com'],
            ];
        }
    };

    expect(Csv::raw($export))->toBe("Name,Email\nJane,jane@example.com\nJohn,john@example.com\n");
});

it('exports collections and iterables', function () {
    $collectionExport = new class implements FromCollection
    {
        public function collection(): Collection
        {
            return collect([
                ['Jane', 'jane@example.com'],
            ]);
        }
    };

    $iterableExport = new class implements FromIterable
    {
        public function iterable(): iterable
        {
            yield ['John', 'john@example.com'];
        }
    };

    expect(Csv::raw($collectionExport))->toBe("Jane,jane@example.com\n")
        ->and(Csv::raw($iterableExport))->toBe("John,john@example.com\n");
});

it('uses custom csv settings', function () {
    $export = new class implements FromArray, WithCustomCsvSettings
    {
        public function getCsvSettings(): array
        {
            return [
                'delimiter' => ';',
                'use_bom' => true,
            ];
        }

        public function array(): array
        {
            return [['Jane', 'jane@example.com']];
        }
    };

    expect(Csv::raw($export))->toBe("\xEF\xBB\xBFJane;jane@example.com\n");
});

it('stores csv and returns stored csv metadata', function () {
    Storage::fake('local');

    $stored = Csv::store(new class implements FromArray
    {
        public function array(): array
        {
            return [['Jane']];
        }
    }, 'exports/users.csv', 'local');

    expect($stored)->toBeInstanceOf(StoredCsv::class)
        ->and($stored->stored())->toBeTrue()
        ->and($stored->path())->toBe('exports/users.csv')
        ->and($stored->disk())->toBe('local');

    Storage::disk('local')->assertExists('exports/users.csv');
});

it('writes csv to a temporary file', function () {
    $file = Csv::temporaryFile(new class implements FromArray
    {
        public function array(): array
        {
            return [['Jane']];
        }
    }, 'users.csv');

    expect($file->fileName())->toBe('users.csv')
        ->and(file_get_contents($file->path()))->toBe("Jane\n");
});

it('exports query results with mapping and descending chunk order', function () {
    CsvUser::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
    CsvUser::query()->create(['name' => 'John', 'email' => 'john@example.com']);

    $export = new class implements FromQuery, WithChunkColumn, WithChunkOrder, WithChunkReading, WithHeadings, WithMapping
    {
        public function query(): Builder
        {
            return CsvUser::query();
        }

        public function headings(): array
        {
            return ['Name', 'Email'];
        }

        public function map(mixed $row): array
        {
            return [$row->name, $row->email];
        }

        public function chunkSize(): int
        {
            return 1;
        }

        public function chunkColumn(): string
        {
            return 'id';
        }

        public function chunkAlias(): ?string
        {
            return null;
        }

        public function chunkOrder(): ChunkOrder
        {
            return ChunkOrder::Desc;
        }
    };

    expect(Csv::raw($export))->toBe("Name,Email\nJohn,john@example.com\nJane,jane@example.com\n");
});

it('can export query rows without mapping', function () {
    CsvUser::query()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $export = new class implements FromQuery, WithHeadings
    {
        public function query(): Builder
        {
            return CsvUser::query()->select(['id', 'name', 'email']);
        }

        public function headings(): array
        {
            return ['ID', 'Name', 'Email'];
        }
    };

    expect(Csv::raw($export))->toBe("ID,Name,Email\n1,Jane,jane@example.com\n");
});

it('can enforce strict column counts', function () {
    $export = new class implements FromArray, WithHeadings, WithStrictColumnCount
    {
        public function headings(): array
        {
            return ['Name', 'Email'];
        }

        public function array(): array
        {
            return [['Jane']];
        }
    };

    Csv::raw($export);
})->throws(ColumnCountMismatch::class);
