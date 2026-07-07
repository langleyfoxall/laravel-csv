<?php

namespace LangleyFoxall\LaravelCsv;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CsvServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('csv')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Csv::class);
    }
}
