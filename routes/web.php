<?php

use BeviWebdevJm\HubAuthClient\HubAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/login/hub', [HubAuthController::class, 'redirect'])->name('hub.login');
    Route::get('/login/hub/callback', [HubAuthController::class, 'callback'])->name('hub.callback');
});
