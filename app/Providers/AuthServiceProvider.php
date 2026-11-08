<?php

namespace App\Providers;

use App\Models\Channel;
use App\Models\Message;
use App\Policies\ChannelPolicy;
use App\Policies\MessagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * モデルと認可ポリシーの対応を定義する。
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Channel::class => ChannelPolicy::class,
        Message::class => MessagePolicy::class,
    ];

    /**
     * 認証・認可の追加初期化用メソッド（現在は処理なし）。
     *
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
