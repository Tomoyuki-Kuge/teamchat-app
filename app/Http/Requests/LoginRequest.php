<?php

namespace App\Http\Requests;

use App\Support\AuthValidation;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    // Fortifyのログイン処理に、このアプリの入力チェックを適用します。
    public function rules(): array
    {
        return AuthValidation::rules();
    }

    // 入力エラーには、登録画面と共通の日本語メッセージを使います。
    public function messages(): array
    {
        return AuthValidation::messages();
    }
}
