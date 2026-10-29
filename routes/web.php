<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

// トップページへのアクセスは、チャンネル一覧へ案内します。
Route::get('/', fn () => redirect()->route('channels.index'));

// 未ログインの人向けに、ログイン・新規登録の画面と送信先を用意します。
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')->name('login.store');

    if (Features::enabled(Features::registration())) {
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    }
});

// ログイン済みの人だけが、一覧表示とログアウトを利用できます。
Route::middleware('auth')->group(function () {
    Route::view('/channels', 'channels.index')->name('channels.index');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
