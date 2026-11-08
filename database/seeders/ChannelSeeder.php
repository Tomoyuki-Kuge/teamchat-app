<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 初期チャンネルと所属メンバーを登録する。
 */
class ChannelSeeder extends Seeder
{
    /**
     * 未削除の同名チャンネルがない場合に、初期設定と所属を作成する。
     */
    public function run(): void
    {
        foreach ([
            ['全体連絡', '全員に共有したいこと', false, 'suzuki@example.com'],
            ['開発', '開発に関する相談と報告', false, 'sato@example.com'],
            ['雑談', 'なんでもどうぞ', false, 'takahashi@example.com'],
            ['採用プロジェクト', '採用まわりの進行', true, 'sato@example.com'],
        ] as [$name, $description, $isPrivate, $email]) {
            DB::transaction(function () use ($name, $description, $isPrivate, $email) {
                if (Channel::active()->where('name', $name)->exists()) {
                    return;
                }
                $creator = User::where('email', $email)->sole();
                $channel = new Channel(['name' => $name, 'description' => $description, 'is_private' => $isPrivate]);
                $channel->creator()->associate($creator);
                $channel->save();
                $members = [$creator->id];
                if ($isPrivate) {
                    $members[] = User::where('email', 'suzuki@example.com')->sole()->id;
                }
                $channel->members()->attach($members);
            });
        }
    }
}
