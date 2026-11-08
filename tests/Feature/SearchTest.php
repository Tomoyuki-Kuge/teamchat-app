<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function channel(User $user, bool $private = false, string $name = '開発'): Channel
    {
        return app(SaveChannel::class)->create($user, ['name' => $name, 'is_private' => $private ? '1' : '0']);
    }

    private function message(Channel $channel, User $user, array $values = []): Message
    {
        $message = new Message;
        $message->forceFill($values + ['channel_id' => $channel->id, 'user_id' => $user->id, 'body' => '検索対象',
            'created_at' => '2026-10-30 00:12:00', 'version' => 1])->save();

        return $message->refresh();
    }

    private function search(array $query = [])
    {
        return $this->get('/search?'.http_build_query($query + ['q' => '検索対象']));
    }

    private function ids($response): array
    {
        return $response->viewData('results')->modelKeys();
    }

    public function test_ac_6_1_only_visible_undeleted_bodies_are_counted_and_deleted_parents_do_not_hide_replies(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $public = $this->channel($owner);
        $joined = $this->channel($owner, true, '参加チャンネル');
        $joined->members()->attach($reader);
        $hidden = $this->channel($owner, true, '秘密の部屋');
        $deleted = $this->channel($owner, false, '終了');
        $deleted->forceFill(['deleted_at' => now()])->save();
        $post = $this->message($public, $owner);
        $private = $this->message($joined, $owner);
        $parent = $this->message($public, $owner, ['body' => '削除原文', 'deleted_at' => now()]);
        $reply = $this->message($public, $owner, ['parent_message_id' => $parent->id]);
        $this->message($public, $owner, ['body' => '検索対象 削除本文', 'deleted_at' => now()]);
        $this->message($deleted, $owner);
        $secret = $this->message($hidden, $owner, ['body' => '検索対象 秘密本文']);
        $unmatched = $this->message($public, $owner, ['body' => '本文不一致']);
        $this->assertGreaterThan($reply->id, $unmatched->id);
        $this->actingAs($reader);
        $response = $this->search()->assertOk()->assertViewHas('total', 3)->assertViewHas('until', $reply->id)
            ->assertDontSee('秘密の部屋')->assertDontSee('秘密本文')->assertDontSee('削除原文')->assertDontSee('削除本文');
        $this->assertSame([$reply->id, $private->id, $post->id], $this->ids($response));
        $this->assertSame([$reply->id, $private->id, $post->id], $this->ids($this->search(['until_id' => $secret->id])));
    }

    public function test_ac_6_4_takahashi_sees_zero_without_disclosing_private_channel(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create(['name' => '高橋 健']);
        $channel = $this->channel($owner, true, '採用プロジェクト');
        $this->message($channel, $owner, ['body' => '機密採用情報']);
        $this->actingAs($reader)->search(['q' => '機密採用情報'])->assertOk()->assertViewHas('until', 0)
            ->assertViewHas('total', 0)->assertDontSee('採用プロジェクト')->assertSee('に一致するメッセージはありません');
    }

    public function test_ac_6_2_result_links_focus_old_posts_and_replies_in_actual_conversation_ranges(): void
    {
        $user = User::factory()->create(['name' => '鈴木 花子']);
        $channel = $this->channel($user);
        $post = $this->message($channel, $user);
        $reply = $this->message($channel, $user, ['parent_message_id' => $post->id]);
        for ($i = 0; $i < 15; $i++) {
            $this->message($channel, $user, ['body' => '後続投稿']);
            $this->message($channel, $user, ['body' => '後続返信', 'parent_message_id' => $post->id]);
        }
        $this->actingAs($user);
        $response = $this->search()->assertOk()->assertSee('鈴木 花子')->assertSee('2026/10/30 09:12');
        foreach ([$post, $reply] as $message) {
            $url = route($message->parent_message_id ? 'threads.show' : 'channels.show',
                ['channel' => $channel->id, 'focus' => $message->id] + ($message->parent_message_id ? ['message' => $post->id] : []));
            $response->assertSee($url.'#message-'.$message->id);
            $focused = $this->get($url)->assertOk();
            $range = $focused->viewData('range');
            $this->assertSame([$message->id], ($message->parent_message_id ? $range->replies : $range)->messages->modelKeys());
            $focused->assertSee('id="message-'.$message->id.'"', false);
        }
    }

    public function test_ac_6_3_pagination_uses_actual_date_id_order_and_keeps_new_ids_out_until_research(): void
    {
        $user = User::factory()->create();
        $channel = $this->channel($user);
        $ids = [];
        for ($i = 0; $i < 23; $i++) {
            $ids[] = $this->message($channel, $user)->id;
        }
        $this->actingAs($user);
        $first = $this->search()->assertOk()->assertViewHas('total', 23);
        $this->assertSame(array_slice(array_reverse($ids), 0, 10), $this->ids($first));
        $until = $first->viewData('until');
        $this->assertSame(max($ids), $until);
        $new = $this->message($channel, $user, ['created_at' => '2026-10-31 00:00:00']);
        foreach (['newest' => array_reverse($ids), 'oldest' => $ids] as $sort => $expected) {
            foreach ([1, 2, 3] as $page) {
                $continued = $this->search(['sort' => $sort, 'page' => $page, 'until_id' => $until])
                    ->assertOk()->assertViewHas('total', 23)->assertViewHas('until', $until)
                    ->assertViewHas('lastPage', 3);
                $this->assertSame(array_slice($expected, ($page - 1) * 10, 10), $this->ids($continued));
                $this->assertNotContains($new->id, $this->ids($continued));
            }
        }
        $refreshed = $this->search()->assertOk()->assertViewHas('total', 24)->assertViewHas('until', $new->id);
        $this->assertSame([$new->id, ...array_slice(array_reverse($ids), 0, 9)], $this->ids($refreshed));
        $second = $this->search(['page' => 2, 'until_id' => end($ids)])->assertOk();
        $this->assertSame(array_slice(array_reverse($ids), 10, 10), $this->ids($second));
        $second->assertSee('until_id='.end($ids))->assertSee('sort=newest')->assertSee('前へ')->assertSee('次へ');
        $refreshedOldest = [...$ids, $new->id];
        foreach ([1, 2, 3] as $page) {
            $oldest = $this->search(['sort' => 'oldest', 'page' => $page])->assertOk()
                ->assertViewHas('total', 24)->assertViewHas('until', $new->id);
            $this->assertSame(array_slice($refreshedOldest, ($page - 1) * 10, 10), $this->ids($oldest));
        }
        $this->search(['q' => '別の語'])->assertOk()->assertViewHas('until', 0);
        $this->search()->assertOk()->assertViewHas('until', $new->id);
        // 再検索と並べ替えのフォームはpage/untilを送らず、新しい上限を取ります。
        preg_match_all('/<form[^>]*method="GET".*?<\/form>/s', $first->getContent(), $forms);
        foreach ($forms[0] as $form) {
            $this->assertStringNotContainsString('name="until_id"', $form);
            $this->assertStringNotContainsString('name="page"', $form);
        }
    }

    public function test_visibility_deletion_and_body_filters_apply_before_counting_and_paginating_in_both_orders(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $public = $this->channel($owner);
        $joined = $this->channel($owner, true, '参加済み');
        $joined->members()->attach($reader);
        $hidden = $this->channel($owner, true, '非開示チャンネル');
        $deleted = $this->channel($owner, false, '削除チャンネル');
        $deleted->forceFill(['deleted_at' => '2026-10-30 12:00:00'])->save();
        $expectedOldest = [];
        // 23一致 + 184除外。閲覧可能・未削除だけでも138件あり、本文不一致が取得境界をまたぐ。
        for ($i = 0; $i < 23; $i++) {
            $date = sprintf('2026-10-30 00:%02d:00', intdiv($i, 2));
            $values = ['created_at' => $date];
            $this->message($hidden, $owner, $values + ['body' => '検索対象 非開示本文']);
            $this->message($public, $owner, $values + ['body' => '検索対象 個別削除本文', 'deleted_at' => '2026-10-30 12:00:00']);
            $this->message($public, $owner, $values + ['body' => '不一致本文']);
            $expectedOldest[] = $this->message($i % 2 === 0 ? $public : $joined, $owner, $values)->id;
            $this->message($deleted, $owner, $values + ['body' => '検索対象 終了本文']);
            for ($j = 0; $j < 4; $j++) {
                $this->message($joined, $owner, $values + ['body' => '不一致本文']);
            }
        }
        $until = max($expectedOldest);
        $unmatched = $this->message($public, $owner, ['body' => '上限に含めない本文', 'created_at' => '2026-10-31 00:00:00']);
        $this->assertGreaterThan($until, $unmatched->id);
        $this->actingAs($reader);
        foreach (['oldest' => $expectedOldest, 'newest' => array_reverse($expectedOldest)] as $sort => $expected) {
            foreach ([1 => 10, 2 => 10, 3 => 3] as $page => $count) {
                $query = ['sort' => $sort, 'page' => $page];
                if ($page > 1) {
                    $query['until_id'] = $until;
                }
                $response = $this->search($query)->assertOk()->assertViewHas('total', 23)
                    ->assertViewHas('until', $until)->assertViewHas('page', $page)->assertViewHas('lastPage', 3)
                    ->assertDontSee('非開示チャンネル')->assertDontSee('非開示本文')
                    ->assertDontSee('個別削除本文')->assertDontSee('削除チャンネル')->assertDontSee('終了本文')
                    ->assertDontSee('不一致本文')->assertDontSee('上限に含めない本文');
                $this->assertCount($count, $this->ids($response));
                $this->assertSame(array_slice($expected, ($page - 1) * 10, 10), $this->ids($response));
            }
        }
    }

    public function test_keyset_batches_preserve_mixed_date_and_id_order_in_both_directions(): void
    {
        $user = User::factory()->create();
        $channel = $this->channel($user);
        $records = [];
        for ($i = 0; $i < 205; $i++) {
            $date = $i < 120 ? '2026-10-30 00:00:00' : ($i % 2 === 0 ? '2026-10-29 00:00:00' : '2026-10-31 00:00:00');
            $message = $this->message($channel, $user, ['created_at' => $date]);
            $records[] = ['date' => $date, 'id' => $message->id];
        }
        $until = $message->id;
        usort($records, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
        $ordered = array_column($records, 'id');
        $this->actingAs($user);
        foreach (['oldest' => $ordered, 'newest' => array_reverse($ordered)] as $sort => $expected) {
            foreach ([1, 10, 11, 20, 21] as $page) {
                $response = $this->search(['sort' => $sort, 'page' => $page, 'until_id' => $until])
                    ->assertOk()->assertViewHas('total', 205)->assertViewHas('until', $until);
                $this->assertSame(array_slice($expected, ($page - 1) * 10, 10), $this->ids($response));
            }
        }
    }

    public function test_current_body_deletion_and_membership_are_rechecked_and_out_of_range_redirects_preserve_context(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $channel = $this->channel($owner, true);
        $channel->members()->attach($reader);
        $messages = new Collection;
        for ($i = 0; $i < 11; $i++) {
            $messages->push($this->message($channel, $owner));
        }
        $until = $messages->last()->id;
        $this->actingAs($reader)->search(['sort' => 'oldest'])->assertViewHas('total', 11);
        $messages[0]->forceFill(['body' => '変更済み'])->save();
        $messages[1]->forceFill(['deleted_at' => now()])->save();
        $snapshot = Message::orderBy('id')->get()->toArray();
        $query = ['q' => '検索対象', 'sort' => 'oldest', 'until_id' => $until];
        $response = $this->search($query + ['page' => 2])->assertStatus(302)
            ->assertRedirect(route('search', ['q' => '検索対象', 'sort' => 'oldest', 'page' => 1, 'until_id' => $until]));
        $this->assertSame($messages->slice(2)->values()->modelKeys(), $this->ids($this->get($response->headers->get('Location'))->assertViewHas('total', 9)));
        $channel->members()->detach($reader);
        $response = $this->search($query + ['page' => PHP_INT_MAX])->assertStatus(302);
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('total', 0)->assertViewHas('until', $until);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_authentication_invalid_input_zero_upper_bound_and_long_literal_queries(): void
    {
        $this->search()->assertRedirect('/login');
        $user = User::factory()->create();
        $channel = $this->channel($user);
        $message = $this->message($channel, $user, ['body' => 'ＡＢＣ ｶﾞ① 100%_ <script>alert(1)</script>']);
        $snapshot = $message->toArray();
        $this->message($channel, $user, ['body' => 'abc ガ1 100xy 通常文字']);
        $this->actingAs($user);
        foreach ([['q' => ''], ['q' => " \n　"], ['q' => ['x']], ['sort' => ['newest']], ['sort' => 'invalid'],
            ['page' => 0], ['page' => '-1'], ['page' => '1.1'], ['page' => ['x']], ['page' => ''],
            ['page' => '999999999999999999999'], ['until_id' => -1], ['until_id' => ['x']], ['until_id' => '1e2']] as $query) {
            $this->search($query)->assertStatus(422)->assertViewHas('total', 0);
        }
        $this->get('/search')->assertStatus(422)->assertSee('検索キーワードを入力してください');
        $this->search(['q' => 'abc ガ 1 % _'])->assertOk()->assertViewHas('total', 1)->assertDontSee('<script>', false)
            ->assertSee('<mark>ＡＢＣ</mark>', false)->assertSee('<mark>ｶﾞ①</mark>', false);
        $this->search(['q' => '%_'])->assertOk()->assertViewHas('total', 1);
        $this->search(['q' => 'abc missing'])->assertOk()->assertViewHas('total', 0);
        $this->search(['q' => str_repeat('a', 2000)])->assertOk();
        $this->search(['until_id' => 0])->assertOk()->assertViewHas('total', 0)->assertViewHas('until', 0);
        $this->assertSame($snapshot, $message->fresh()->toArray());
    }

    public function test_repeated_normalized_words_keep_original_input_and_and_matching(): void
    {
        $user = User::factory()->create();
        $channel = $this->channel($user);
        $body = str_repeat('a', 1000);
        $match = $this->message($channel, $user, ['body' => $body]);
        $query = str_repeat('Ａ a ', 300);
        $this->actingAs($user);
        $response = $this->search(['q' => $query])->assertOk()->assertViewHas('q', $query)
            ->assertViewHas('words', ['a'])->assertViewHas('total', 1)
            ->assertSee('value="'.$query.'"', false)->assertSee('<mark>'.$body.'</mark>', false);
        $this->assertSame([$match->id], $this->ids($response));
        $this->search(['q' => $query.' missing'])->assertOk()->assertViewHas('total', 0);
        $this->assertSame($body, $match->fresh()->body);
    }
}
