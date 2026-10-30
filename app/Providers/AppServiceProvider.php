<?php

namespace App\Providers;

use App\Models\Channel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
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
