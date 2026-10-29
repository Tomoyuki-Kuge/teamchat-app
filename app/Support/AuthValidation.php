<?php

namespace App\Support;

use Closure;

class AuthValidation
{
    // 共通の入力チェックに、登録時だけ名前・重複・パスワード確認を追加します。
    public static function rules(bool $register = false): array
    {
        // 入力値そのものは変えず、空白だけの入力をエラーにします。
        $notBlank = function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && AuthInput::trim($value) === '') {
                $fail(self::messages()[$attribute.'.required']);
            }
        };

        $rules = [
            'email' => ['bail', 'required', 'string', 'max:255', 'not_regex:/[\\r\\n]/', 'email:rfc'],
            'password' => ['bail', 'required', 'string', $notBlank],
        ];

        if ($register) {
            $rules['name'] = ['bail', 'required', 'string', $notBlank, 'max:30'];
            $rules['email'][] = 'unique:users,email';
            $rules['password'][] = 'min:8';
            $rules['password'][] = 'max:72';
            $rules['password_confirmation'] = ['bail', 'required', 'string', $notBlank, 'same:password'];
        }

        return $rules;
    }

    // Laravelの入力チェックで表示する、日本語のエラーメッセージです。
    public static function messages(): array
    {
        return [
            'name.required' => '表示名を入力してください',
            'name.string' => '表示名を入力してください',
            'name.max' => '表示名は30文字以内で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.string' => 'メールアドレスを正しい形式で入力してください',
            'email.not_regex' => 'メールアドレスを正しい形式で入力してください',
            'email.email' => 'メールアドレスを正しい形式で入力してください',
            'email.max' => 'メールアドレスは255文字以内で入力してください',
            'email.unique' => 'このメールアドレスは登録されています',
            'password.required' => 'パスワードを入力してください',
            'password.string' => 'パスワードを入力してください',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'password.max' => 'パスワードは72文字以内で入力してください',
            'password_confirmation.required' => 'パスワード（確認用）を入力してください',
            'password_confirmation.string' => 'パスワード（確認用）を入力してください',
            'password_confirmation.same' => 'パスワード（確認用）が一致しません',
        ];
    }
}
