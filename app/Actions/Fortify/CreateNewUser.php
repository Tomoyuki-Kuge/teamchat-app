<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\AuthInput;
use App\Support\AuthValidation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    // 登録内容を検証し、パスワードを元に戻せないハッシュ値にして保存します。
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = AuthInput::email($input['email']);
        }

        $validated = Validator::make($input, AuthValidation::rules(true), AuthValidation::messages())->validate();

        try {
            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            // 同時登録でメールが重複した場合も、入力エラーとして画面に返します。
            throw ValidationException::withMessages(['email' => 'このメールアドレスは登録されています']);
        }
    }
}
