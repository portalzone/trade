<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to T-Trade API',
        'version' => '1.0.0',
        'docs' => url('/api/documentation')
    ]);
});
