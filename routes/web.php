<?php

use Illuminate\Support\Facades\Route;

// Single Page Application catch-all route
Route::view('/{any?}', 'app')->where('any', '.*');
