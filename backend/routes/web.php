<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ReceiptController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verify-receipt/{token}', [ReceiptController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('web.verify-receipt');
