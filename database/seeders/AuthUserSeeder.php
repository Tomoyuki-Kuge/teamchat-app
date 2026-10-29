<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthUserSeeder extends Seeder
{
    // 動作確認用の3アカウントを、既存のユーザーを上書きせずに用意します。
    public function run(): void
    {
        foreach ([
            'sato@example.com' => '佐藤 太郎',
            'suzuki@example.com' => '鈴木 花子',
            'takahashi@example.com' => '高橋 健',
        ] as $email => $name) {
            User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password_hash' => Hash::make('password'),
            ]);
        }
    }
}
