<?php

namespace App\Providers;

use App\Models\Channel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * 共通レイアウトへの表示データ設定を行う。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * 追加のサービス登録処理は定義していない。
     *
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * 共通レイアウトのサイドバー用チャンネル一覧を設定する。
     *
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = request()->user();
            $channels = $view->getData()['channels'] ?? null;
            $view->with('sidebarChannels', $channels ?? Channel::visibleTo($user)->inCreationOrder()->get());
        });
    }
}
