<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as ResponseContract;

/**
 * Fortifyのログアウト後の応答を定義する。
 */
class LogoutResponse implements ResponseContract
{
    // ログアウト後は、GETでログイン画面へ移動します（303）。
    public function toResponse($request)
    {
        return redirect()->route('login', [], 303);
    }
}
