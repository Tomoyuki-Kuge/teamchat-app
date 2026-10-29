<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as ResponseContract;

class LogoutResponse implements ResponseContract
{
    // ログアウト後は、GETでログイン画面へ移動します（303）。
    public function toResponse($request)
    {
        return redirect()->route('login', [], 303);
    }
}
