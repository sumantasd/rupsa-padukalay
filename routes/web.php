<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — RUPSA PADUKALAYA
|--------------------------------------------------------------------------
*/

// Public Customer Website Routes
Route::get('/', function () {
    return view('public');
});

Route::get('/about', function () {
    return view('public');
});

Route::get('/products/{any?}', function () {
    return view('public');
})->where('any', '.*');

Route::get('/categories/{any?}', function () {
    return view('public');
})->where('any', '.*');

Route::get('/offers', function () {
    return view('public');
});

Route::get('/contact', function () {
    return view('public');
});

Route::get('/stores', function () {
    return view('public');
});

// Dedicated Admin ERP & Web POS Fallback Route (Mounts Admin Vue SPA)
Route::get('/admin/{any?}', function () {
    return view('app');
})->where('any', '.*');
