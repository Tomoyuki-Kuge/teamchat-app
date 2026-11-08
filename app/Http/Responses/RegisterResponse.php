<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as ResponseContract;

/**
 * Fortifyの登録成功時の応答を定義する。
 */
class RegisterResponse implements ResponseContract
{
    // 登録成功後は、GETでチャンネル一覧へ移動します（303）。
    public function toResponse($request)
    {
        return redirect()->route('channels.index', [], 303);
    }
}
