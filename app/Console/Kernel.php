<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * コンソールコマンドの登録と定期実行の設定を担う。
 */
class Kernel extends ConsoleKernel
{
    /**
     * コマンドの定期実行を定義する（現在は登録なし）。
     *
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
    }

    /**
     * コマンドクラスとコンソール用ルート定義を読み込む。
     *
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
