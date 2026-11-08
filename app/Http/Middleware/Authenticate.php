<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * ログインが必要なリクエストの認証状態を確認する。
 */
class Authenticate extends Middleware
{
    /**
     * 未認証の画面リクエストに使う転送先を返す。
     *
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}
