<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

/**
 * 指定項目を除き、文字列入力の前後空白を取り除く。
 */
class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        // 表示名は入力を保持し、メールの空白は専用の処理で取り除きます。
        'name',
        'body',
        'q',
        'confirmation_name',
        'email',
        'current_password',
        'password',
        'password_confirmation',
    ];
}
