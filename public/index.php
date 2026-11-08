<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// 処理時間の計測に使う開始時刻を記録します。
define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

// メンテナンス用ファイルがある場合に読み込みます。
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

// Composerのクラス自動読み込みを有効にします。
require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

// 起動設定を読み込み、Laravelのアプリ本体を生成します。
$app = require_once __DIR__.'/../bootstrap/app.php';

// HTTPリクエストを処理するKernelを取得します。
$kernel = $app->make(Kernel::class);

// 受信したリクエストを処理し、生成した応答を送信します。
$response = $kernel->handle(
    $request = Request::capture()
)->send();

// 応答の送信後にKernelの終了処理を実行します。
$kernel->terminate($request, $response);
