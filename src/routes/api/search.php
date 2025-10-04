<?php

use Illuminate\Support\Facades\Route;
use Backpack\Store\app\Http\Controllers\Api\SearchController;

Route::get('search/products', [SearchController::class, 'products']);
