<?php

use App\Http\Controllers\Api\PublicChannelController;
use Illuminate\Support\Facades\Route;

Route::get('/channels', [PublicChannelController::class, 'index'])->name('api.channels.index');
Route::get('/channels/{channel}/messages', [PublicChannelController::class, 'messages'])->name('api.messages.index');
