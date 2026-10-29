<?php

namespace App\Http\Middleware;

use App\Support\AuthInput;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeAuthInput
{
    // 登録・ログインの入力を、検証や回数制限より前に整えます。
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('login.store', 'register.store')) {
            return $next($request);
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => AuthInput::email($request->input('email'))]);
        }

        // 今回はログイン状態の永続化を扱わないため、remember指定を無効にします。
        $request->merge(['remember' => false]);

        return $next($request);
    }
}
