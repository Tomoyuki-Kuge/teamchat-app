<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\MessageController;
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

// ログイン済みの人だけが、チャンネルとログアウトを利用できます。
Route::middleware('auth')->group(function () {
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::get('/channels/create', [ChannelController::class, 'create'])->name('channels.create');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::get('/channels/{channel}', [ChannelController::class, 'show'])->whereNumber('channel')->name('channels.show');
    Route::get('/channels/{channel}/edit', [ChannelController::class, 'edit'])->whereNumber('channel')->name('channels.edit');
    Route::patch('/channels/{channel}', [ChannelController::class, 'update'])->whereNumber('channel')->name('channels.update');
    Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->whereNumber('channel')->name('channels.destroy');
    Route::post('/channels/{channel}/messages', [MessageController::class, 'store'])->whereNumber('channel')->name('messages.store');
    Route::get('/channels/{channel}/messages/{message}/delete-confirmation', [MessageController::class, 'deleteConfirmation'])->whereNumber(['channel', 'message'])->name('messages.delete-confirmation');
    Route::patch('/channels/{channel}/messages/{message}', [MessageController::class, 'update'])->whereNumber(['channel', 'message'])->name('messages.update');
    Route::delete('/channels/{channel}/messages/{message}', [MessageController::class, 'destroy'])->whereNumber(['channel', 'message'])->name('messages.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
