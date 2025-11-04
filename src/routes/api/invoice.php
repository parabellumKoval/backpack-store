<?php

use Illuminate\Support\Facades\Route;
use Backpack\Store\app\Http\Controllers\Api\InvoiceController;

// $guard = \Settings::get('dress.store.auth_guard', 'profile');
$guard = 'sanctum';

Route::prefix('api/store/invoices')
    ->middleware([
        'api',
        Backpack\Store\app\Http\Middleware\AddXRegionHeadersToRequest::class,
    ])
    ->controller(InvoiceController::class)
    ->group(function () use ($guard) {
        Route::get('{order}', 'preview');
            // ->middleware("auth:{$guard}");

        Route::get('{order}/download', 'download');
            // ->middleware("auth:{$guard}");

        Route::get('{order}/qr', 'qr');
            // ->middleware("auth:{$guard}");

        Route::get('{order}/debug', 'previewFresh');

        Route::get('{order}/template', 'template');
            // ->middleware("auth:{$guard}");

        Route::get('{order}/signed/{invoice}', 'downloadSigned')
            ->name('backpack.store.invoices.download-signed');
            // ->middleware('signed');
    });
