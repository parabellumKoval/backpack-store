<?php

use Illuminate\Support\Facades\Route;
use Backpack\Store\app\Http\Controllers\Api\ShippingController;

Route::any('api/shipping/quote', [ShippingController::class, 'quote'])->middleware([
    'api',
    'throttle:60,1',
    Backpack\Store\app\Http\Middleware\SetLocaleFromHeader::class,
]);
