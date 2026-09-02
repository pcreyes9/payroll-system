<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::redirect('/', '/time-clock');

Route::view('/time-clock', 'attendance.time-clock')
    ->name('time-clock');
