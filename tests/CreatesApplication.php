<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // テストでも.envを読まず、公開用の設定例を読み込みます。
        $app->loadEnvironmentFrom('.env.example');

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
