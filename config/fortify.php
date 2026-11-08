<?php

use Laravel\Fortify\Features;

// Fortifyの認証設定と有効にする追加機能を定義します。
return [
    // セッションを使う通常のWeb認証としてFortifyを動かします。
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    // ログインIDとメールアドレスの入力項目名を指定します。
    'username' => 'email',
    'email' => 'email',
    // 認証画面の利用と認証後の遷移先を設定します。
    'views' => true,
    'home' => '/channels',
    // メール全体の小文字化を避け、ドメインだけを独自処理で小文字にします。
    'lowercase_usernames' => false,
    // ログイン試行に適用するレート制限の名前を指定します。
    'limiters' => ['login' => 'login'],
    // 追加機能は新規登録だけを有効にします。
    'features' => [
        Features::registration(),
    ],
];
