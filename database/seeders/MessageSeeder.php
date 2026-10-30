<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $authors = User::whereIn('email', ['sato@example.com', 'suzuki@example.com', 'takahashi@example.com'])->get()->keyBy('email');
        $publicAuthors = ['suzuki@example.com', 'sato@example.com', 'takahashi@example.com'];
        foreach ($this->conversations() as $name => $bodies) {
            DB::transaction(function () use ($name, $bodies, $authors, $publicAuthors) {
                $channel = Channel::active()->where('name', $name)->lockForUpdate()->firstOrFail();
                if ($channel->messages()->exists()) {
                    return;
                }
                $emails = $channel->is_private ? array_slice($publicAuthors, 0, 2) : $publicAuthors;
                foreach ($bodies as $index => $body) {
                    $created = CarbonImmutable::parse('2026-10-28 09:10:00', 'Asia/Tokyo')->addDays(intdiv($index, 5))->addMinutes(($index % 5) * 37)->utc();
                    $message = new Message(['body' => $body]);
                    $message->forceFill([
                        'channel_id' => $channel->id, 'user_id' => $authors[$emails[$index % count($emails)]]->id,
                        'parent_message_id' => null, 'version' => 1, 'created_at' => $created,
                        'edited_at' => $index === 1 ? $created->addMinutes(5) : null,
                        'deleted_at' => $index === 2 ? $created->addMinutes(8) : null,
                    ])->save();
                }
            });
        }
    }

    private function conversations(): array
    {
        return [
            '全体連絡' => [
                'おはようございます。今週の全体ミーティングは木曜の15時からです。',
                '議題に新しい開発環境の紹介を追加しました。資料は当日までに共有します。',
                '会議室の予約を確認中です。決まり次第お知らせします。',
                '会議室Aを確保できました。オンラインでも参加できます。',
                '承知しました。今週入社した方にも案内しておきます。',
                '来月の休暇予定を、今週中に各チームで確認してください。',
                '開発チームの予定は確認済みです。引き継ぎの時間も設けます。',
                'ありがとうございます。日程が重なる場合は早めに相談しましょう。',
                'ミーティング資料の準備ができました。質問があればこのチャンネルへお願いします。',
                '資料を確認しました。当日は運用面の補足をします。',
            ],
            '開発' => [
                '次のリリースに向けて、残っている作業を共有してもらえますか。',
                'ログイン画面の調整は完了しました。確認手順も追記しています。',
                '古い検証環境について確認中です。',
                'チャンネル管理のレビューを今日中に返します。',
                'ありがとうございます。検索まわりの実装は木曜に終わる見込みです。',
                'ステージングの設定を更新しました。ログインできるか確認をお願いします。',
                'こちらの環境では問題なくログインできました。',
                'メッセージの表示順も確認しました。同じ日時の投稿も安定して並びます。',
                '明日の午前に結合確認の時間を取りたいです。',
                '10時から参加できます。確認項目を先に整理しておきます。',
            ],
            '雑談' => [
                '駅前に新しい定食屋さんができていました。お昼に行ってみませんか。',
                'いいですね。今日は12時半ごろなら行けそうです。',
                'お店の営業時間を確認しています。',
                '私も参加します。会社の入口で待ち合わせましょう。',
                '日替わり定食がおいしかったです。また行きたいですね。',
                '週末は久しぶりに近所の公園を散歩しました。',
                '最近は涼しくて歩きやすいですね。紅葉も少し始まっています。',
                'おすすめの散歩コースがあれば教えてください。',
                '川沿いの道が静かでおすすめです。駅からも近いですよ。',
                'ありがとうございます。次の休日に歩いてみます。',
            ],
            '採用プロジェクト' => [
                '来月の面接日程について、このチャンネルで相談しましょう。',
                '候補日の一覧を整理しました。まずは火曜と木曜の午後で調整します。',
                '面接の担当表を見直しています。',
                '承知しました。募集内容の説明も次回までに確認しておきます。',
            ],
        ];
    }
}
