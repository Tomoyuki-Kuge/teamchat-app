<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    // Fortifyの標準処理に、今回の入力チェックと画面遷移を組み込みます。
    public function register(): void
    {
        // 不要なURLを公開しないよう、ルートはroutes/web.phpで定義します。
        Fortify::ignoreRoutes();
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
        $this->app->singleton(LoginResponse::class, \App\Http\Responses\LoginResponse::class);
        $this->app->singleton(RegisterResponse::class, \App\Http\Responses\RegisterResponse::class);
        $this->app->singleton(LogoutResponse::class, \App\Http\Responses\LogoutResponse::class);
    }

    // 登録処理・Blade画面・ログイン回数の制限をFortifyに設定します。
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));

        RateLimiter::for('login', function (Request $request) {
            $email = is_string($request->input('email')) ? $request->input('email') : '';
            // 正規化済みメールアドレスとIPの組み合わせごとに試行回数を数えます。
            $key = hash('sha256', json_encode([$email, $request->ip()]));

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                return response()->view('errors.429', [], 429, $headers);
            });
        });
    }
}
