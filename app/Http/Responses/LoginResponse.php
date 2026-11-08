<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as ResponseContract;

/**
 * Fortifyのログイン成功後の応答を定義する。
 */
class LoginResponse implements ResponseContract
{
    // ログイン成功後は、GETでチャンネル一覧へ移動します（303）。
    public function toResponse($request)
    {
        return redirect()->route('channels.index', [], 303);
    }
}
