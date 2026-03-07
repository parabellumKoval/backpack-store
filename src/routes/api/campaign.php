<?php

use Backpack\Store\app\Http\Controllers\Api\CampaignController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/campaign')->middleware([
    'api',
    Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class,
    Backpack\Store\app\Http\Middleware\SetLocaleFromHeader::class,
])->controller(CampaignController::class)->group(function () {
    Route::get('', 'index');
    Route::get('/{slug}', 'show');
});
