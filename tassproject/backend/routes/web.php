<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/{any}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '.*');
