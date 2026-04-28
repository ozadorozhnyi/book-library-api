<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => config('app.name'),
    'documentation' => url('/api/documentation'),
    'api_root' => url('/api/v1'),
    'health' => url('/up'),
]));
