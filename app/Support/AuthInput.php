<?php

namespace App\Support;

class AuthInput
{
    // 文字列の前後にある空白を、全角空白も含めて取り除きます。
    public static function trim(string $value): string
    {
        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value) ?? $value;
    }

    // メールの前後の空白を除き、@より後ろだけを小文字にそろえます。
    public static function email(string $value): string
    {
        $value = self::trim($value);
        $separator = strrpos($value, '@');

        return $separator === false ? $value : substr($value, 0, $separator + 1).strtolower(substr($value, $separator + 1));
    }
}
