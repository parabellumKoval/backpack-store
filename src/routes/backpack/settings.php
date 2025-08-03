<?php

Route::middleware(['web', 'admin'])
    ->prefix(config('backpack.base.route_prefix'))
    ->name('backpack.')
    ->group(function () {
        Route::get('search-settings', [\Backpack\Store\app\Http\Controllers\Admin\Settings\SearchController::class, 'edit'])->name('search-settings');
        Route::post('search-settings', [\Backpack\Store\app\Http\Controllers\Admin\Settings\SearchController::class, 'update']);
    });