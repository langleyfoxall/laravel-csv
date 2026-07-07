# Laravel CSV

A small Laravel 12+ package for streaming CSV exports and imports without pulling in spreadsheet support.

## Installation

```bash
composer require langleyfoxall/laravel-csv
```

Publish config when you need global defaults:

```bash
php artisan vendor:publish --tag="csv-config"
```

## Exporting

Create an export class with one source concern:

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

Use the facade:

```php
use LangleyFoxall\LaravelCsv\Facades\Csv;

return Csv::download(new UsersExport, 'users.csv');

$csv = Csv::raw(new UsersExport);

$stored = Csv::store(new UsersExport, 'exports/users.csv', disk: 's3');
```

`store()` returns a `StoredCsv`:

```php
$stored->path();
$stored->disk();
$stored->stored();
```

For Spatie Media Library:

```php
$stored = Csv::store(new UsersExport, 'exports/users.csv');

$model
    ->addMediaFromDisk($stored->path(), $stored->disk())
    ->toMediaCollection('exports');
```

Or create a temp file:

```php
$file = Csv::temporaryFile(new UsersExport, 'users.csv');

$model
    ->addMedia($file->path())
    ->usingFileName($file->fileName())
    ->toMediaCollection('exports');
```

Supported export sources:

- `FromArray`
- `FromCollection`
- `FromIterable`
- `FromQuery`

Query exports stream with `lazyById()` by default. Use `WithChunkReading`, `WithChunkColumn`, and `WithChunkOrder` to control chunk size, column, alias, and ascending/descending order. Query exports without `WithMapping` work, but should use explicit `select([...])` including the chunk column.

## Importing

Use `ToCollection` for whole-file or chunked imports:

```php
use Illuminate\Support\Collection;
use LangleyFoxall\LaravelCsv\Concerns\ToCollection;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingRow;

final class UsersImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // $row['email']
        }
    }
}
```

Use `OnEachRow` for streaming row-by-row:

```php
use Illuminate\Support\Collection;
use LangleyFoxall\LaravelCsv\Concerns\OnEachRow;
use LangleyFoxall\LaravelCsv\Concerns\WithHeadingRow;

final class UsersImport implements OnEachRow, WithHeadingRow
{
    public function onRow(Collection $row, int $rowNumber): void
    {
        // Validate and persist in app code.
    }
}
```

Run import:

```php
Csv::import(new UsersImport, 'imports/users.csv', disk: 'local');
```

Import behavior:

- `WithHeadingRow` maps data rows by normalized headings.
- duplicate normalized headings throw `DuplicateHeading`.
- empty rows are skipped by default.
- `PreservesEmptyRows` keeps empty rows.
- `WithChunkReading` chunks `ToCollection` imports.
- validation belongs in app code.

## CSV Settings

Default settings live in `config/csv.php`:

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

Override per export/import with `WithCustomCsvSettings`.

## Testing

```bash
composer test
```
