# Laravel CSV

Small Laravel 12+ package for streaming CSV exports and imports. It uses PHP CSV functions, Laravel storage, and simple concern classes. No spreadsheet engine.

## Requirements

- PHP `^8.3`
- Laravel `^12.0`

## Installation

```bash
composer require langleyfoxall/laravel-csv
```

Publish config when app-wide defaults need changing:

```bash
php artisan vendor:publish --tag="csv-config"
```

## Quick Start

```php
use LangleyFoxall\LaravelCsv\Facades\Csv;

return Csv::download(new UsersExport, 'users.csv');

$csv = Csv::raw(new UsersExport);

$stored = Csv::store(new UsersExport, 'exports/users.csv', disk: 's3');

Csv::import(new UsersImport, 'imports/users.csv', disk: 'local');
```

## Facade API

```php
Csv::raw(object $export): string

Csv::download(
    object $export,
    string $fileName,
    array $headers = [],
): Symfony\Component\HttpFoundation\StreamedResponse

Csv::store(
    object $export,
    string $path,
    ?string $disk = null,
    array $options = [],
): LangleyFoxall\LaravelCsv\Support\StoredCsv

Csv::temporaryFile(
    object $export,
    ?string $fileName = null,
): LangleyFoxall\LaravelCsv\Support\TemporaryCsv

Csv::import(
    object $import,
    string $path,
    ?string $disk = null,
): void
```

## Exports

Each export must implement one source concern.

### `FromArray`

```php
use LangleyFoxall\LaravelCsv\Concerns\FromArray;

final class UsersExport implements FromArray
{
    public function array(): array
    {
        return [
            ['Jane', 'jane@example.com'],
            ['John', 'john@example.com'],
        ];
    }
}
```

### `FromCollection`

```php
use Illuminate\Support\Collection;
use LangleyFoxall\LaravelCsv\Concerns\FromCollection;

final class UsersExport implements FromCollection
{
    public function collection(): Collection
    {
        return User::query()->select(['name', 'email'])->get();
    }
}
```

### `FromIterable`

```php
use LangleyFoxall\LaravelCsv\Concerns\FromIterable;

final class UsersExport implements FromIterable
{
    public function iterable(): iterable
    {
        foreach (User::query()->cursor() as $user) {
            yield [$user->name, $user->email];
        }
    }
}
```

### `FromQuery`

`FromQuery` streams with `lazyById()` or `lazyByIdDesc()`.

```php
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use LangleyFoxall\LaravelCsv\Concerns\FromQuery;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadings;
use LangleyFoxall\LaravelCsv\Concerns\WithMapping;

final class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return User::query()->with('company');
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Company'];
    }

    public function map(mixed $row): array
    {
        return [$row->name, $row->email, $row->company?->name];
    }
}
```

Query exports without `WithMapping` are allowed. Use explicit `select([...])` and include chunk column:

```php
final class UsersExport implements FromQuery, WithHeadings
{
    public function query(): Builder
    {
        return User::query()->select(['id', 'name', 'email']);
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email'];
    }
}
```

### Export Concerns

`WithHeadings` writes header row:

```php
public function headings(): array;
```

`WithMapping` maps each source row:

```php
public function map(mixed $row): array;
```

`WithChunkReading` controls query chunk size:

```php
public function chunkSize(): int;
```

`WithChunkColumn` controls query chunk column and alias:

```php
public function chunkColumn(): string;

public function chunkAlias(): ?string;
```

`WithChunkOrder` controls query order:

```php
use LangleyFoxall\LaravelCsv\Enums\ChunkOrder;

public function chunkOrder(): ChunkOrder; // ChunkOrder::Asc or ChunkOrder::Desc
```

`WithStrictColumnCount` throws when data row column count differs from heading count.

`WithCustomCsvSettings` overrides CSV settings per export:

```php
public function getCsvSettings(): array
{
    return [
        'delimiter' => ';',
        'use_bom' => true,
    ];
}
```

## Importing

Imports must implement `ToCollection`, `OnEachRow`, or both.

Run import:

```php
Csv::import(new UsersImport, 'imports/users.csv', disk: 'local');
```

### `ToCollection`

Loads rows into one `Collection`, unless `WithChunkReading` is used.

```php
use Illuminate\Support\Collection;
use LangleyFoxall\LaravelCsv\Concerns\ToCollection;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingRow;

final class UsersImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            User::create([
                'name' => $row['name'],
                'email' => $row['email'],
            ]);
        }
    }
}
```

### `OnEachRow`

Streams one row at a time.

```php
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use LangleyFoxall\LaravelCsv\Concerns\OnEachRow;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingRow;

final class UsersImport implements OnEachRow, WithHeadingRow
{
    public function onRow(Collection $row, int $rowNumber): void
    {
        Validator::make($row->all(), [
            'email' => ['required', 'email'],
        ])->validate();

        User::create($row->only(['name', 'email'])->all());
    }
}
```

### Chunked Imports

```php
use Illuminate\Support\Collection;
use LangleyFoxall\LaravelCsv\Concerns\ToCollection;
use LangleyFoxall\LaravelCsv\Concerns\WithChunkReading;

final class UsersImport implements ToCollection, WithChunkReading
{
    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        // Called once per chunk.
    }
}
```

### Import Concerns

`WithHeadingRow` uses first row as headings. Headings are normalized with `Str::slug($heading, '_')`.

Example:

```txt
First Name,Email Address
Jane,jane@example.com
```

Becomes:

```php
[
    'first_name' => 'Jane',
    'email_address' => 'jane@example.com',
]
```

Duplicate normalized headings throw `DuplicateHeading`.

`WithHeadingFormatter` customizes heading keys:

```php
public function formatHeading(?string $heading): string
{
    return strtoupper(str_replace(' ', '_', (string) $heading));
}
```

`PreservesEmptyRows` keeps blank rows. By default blank rows are skipped.

`WithCustomCsvSettings` overrides CSV settings per import.

Validation is app code. Package only reads and writes CSV transport.

## Stored Files

`Csv::store()` writes to Laravel filesystem and returns `StoredCsv`.

```php
$stored = Csv::store(new UsersExport, 'exports/users.csv', disk: 'local');

$stored->path();   // exports/users.csv
$stored->disk();   // local
$stored->stored(); // true
```

`StoredCsv` can create temporary URLs for disks that support them:

```php
$url = $stored->temporaryUrl(now()->addMinutes(5));
```

## Temporary Files

`Csv::temporaryFile()` writes export to local temp dir and returns `TemporaryCsv`.

```php
$file = Csv::temporaryFile(new UsersExport, 'users.csv');

$file->path();
$file->fileName();
```

Useful for APIs that need a real file path.

## Spatie Media Library

From stored disk path:

```php
$stored = Csv::store(new UsersExport, 'exports/users.csv', disk: 'local');

$model
    ->addMediaFromDisk($stored->path(), $stored->disk())
    ->toMediaCollection('exports');
```

From temp file:

```php
$file = Csv::temporaryFile(new UsersExport, 'users.csv');

$model
    ->addMedia($file->path())
    ->usingFileName($file->fileName())
    ->toMediaCollection('exports');
```

## CSV Settings

Default config:

```php
return [
    'delimiter' => ',',
    'enclosure' => '"',
    'escape_character' => '',
    'line_ending' => PHP_EOL,
    'use_bom' => false,
    'chunk_size' => 1000,
    'chunk_column' => 'id',
    'chunk_alias' => null,
];
```

Notes:

- `escape_character` defaults to empty string for modern RFC-style CSV behavior.
- `use_bom` prepends UTF-8 BOM for Excel compatibility.
- query exports default to chunk column `id`, ascending order, chunk size `1000`.

## Exceptions

All package exceptions extend `LangleyFoxall\LaravelCsv\Exceptions\CsvException`.

- `InvalidExport`
- `InvalidImport`
- `DuplicateHeading`
- `ColumnCountMismatch`
- `UnableToReadFile`
- `UnableToWriteFile`

Example:

```php
use LangleyFoxall\LaravelCsv\Exceptions\CsvException;

try {
    Csv::import(new UsersImport, 'imports/users.csv');
} catch (CsvException $exception) {
    report($exception);
}
```

## Testing

```bash
composer test
composer analyse
composer format
```
