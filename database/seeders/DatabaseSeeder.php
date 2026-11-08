<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 初期データの投入をまとめて実行する。
 */
class DatabaseSeeder extends Seeder
{
    /**
     * アカウント・チャンネル・メッセージの順に初期データを用意する。
     *
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([AuthUserSeeder::class, ChannelSeeder::class, MessageSeeder::class]);
    }
}
