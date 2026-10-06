<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RatingController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/home', function () {
    return view('home');
});

Route::get('/db-all', [App\Http\Controllers\DBController::class, 'showAll']);
// Route::get('/ratings/test', [RatingController::class, 'testList']);
