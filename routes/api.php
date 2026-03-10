<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadCaptureController;

Route::post('/lead-capture', [LeadCaptureController::class, 'store']);
