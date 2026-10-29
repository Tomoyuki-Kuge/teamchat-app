<?php

use Laravel\Fortify\Features;

return [
    // セッションを使う通常のWeb認証としてFortifyを動かします。
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'username' => 'email',
    'email' => 'email',
    'views' => true,
    'home' => '/channels',
    // メール全体の小文字化を避け、ドメインだけを独自処理で小文字にします。
    'lowercase_usernames' => false,
    'limiters' => ['login' => 'login'],
    // 追加機能は新規登録だけを有効にします。
    'features' => [
        Features::registration(),
    ],
];
