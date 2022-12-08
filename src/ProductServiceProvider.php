<?php

namespace ParabellumKoval\Product;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ParabellumKoval\Product\Commands\ProductCommand;

class ProductServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('products-for-backpack')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_products-for-backpack_table')
            ->hasCommand(ProductCommand::class);
    }
}
