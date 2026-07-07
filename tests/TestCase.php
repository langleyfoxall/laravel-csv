<?php

namespace LangleyFoxall\LaravelCsv\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LangleyFoxall\LaravelCsv\CsvServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LangleyFoxall\\LaravelCsv\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Schema::create('csv_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            CsvServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('filesystems.default', 'local');
        config()->set('filesystems.disks.local.root', __DIR__.'/Fixtures/storage');
    }
}
