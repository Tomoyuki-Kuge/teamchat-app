<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

/**
 * ブロードキャストのルートとチャンネル定義の登録を担う。
 */
class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * ブロードキャスト認証ルートを登録し、チャンネル定義を読み込む。
     *
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
