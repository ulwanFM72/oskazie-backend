<?php

use Illuminate\Support\Facades\Route;

Route::fallback(fn() => response()->file(public_path('index.html')));
