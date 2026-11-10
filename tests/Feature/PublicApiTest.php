<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private function channel(array $values = []): Channel
    {
        $channel = new Channel;
        $channel->forceFill($values + [
            'name' => 'チャンネル'.Channel::count(), 'description' => null, 'is_private' => false,
            'creator_id' => User::firstOrFail()->id, 'created_at' => '2026-08-17 00:00:00',
        ])->save();

        return $channel;
    }

    private function message(Channel $channel, array $values = []): Message
    {
        $message = new Message;
        $message->forceFill($values + [
            'channel_id' => $channel->id, 'user_id' => User::firstOrFail()->id,
            'body' => ' 公開本文\n<script> ', 'parent_message_id' => null,
            'created_at' => '2026-08-17 00:12:00', 'version' => 1,
        ])->save();

        return $message;
    }

    private function assertNoDisclosedValues(array $payload, array $forbidden): void
    {
        // JSONのUnicodeエスケープに左右されないよう、デコード済みの値を再帰的に確認します。
        array_walk_recursive($payload, function ($value) use ($forbidden) {
            if (is_string($value)) {
                foreach ($forbidden as $secret) {
                    $this->assertStringNotContainsString($secret, $value);
                }
            }
        });
    }

    private function assertSuccessContract(array $payload, bool $messages, array $forbidden = []): void
    {
        $this->assertEqualsCanonicalizing(['data', 'meta', 'links'], array_keys($payload));
        $this->assertEqualsCanonicalizing(['prev', 'next'], array_keys($payload['links']));
        $this->assertEqualsCanonicalizing($messages
            ? ['current_page', 'until_id', 'per_type', 'messages_total', 'replies_total', 'last_page']
            : ['current_page', 'until_id', 'per_page', 'total', 'last_page'], array_keys($payload['meta']));
        if ($messages) {
            $this->assertEqualsCanonicalizing(['messages', 'replies'], array_keys($payload['data']));
        }
        foreach ($messages ? $payload['data'] : [$payload['data']] as $rows) {
            $this->assertTrue(array_is_list($rows));
            foreach ($rows as $row) {
                $this->assertEqualsCanonicalizing($messages
                    ? ['id', 'parent_message_id', 'body', 'author_name', 'created_at', 'is_edited', 'reply_count']
                    : ['id', 'name', 'description'], array_keys($row));
                $this->assertIsInt($row['id']);
                if ($messages) {
                    $this->assertIsBool($row['is_edited']);
                    $this->assertIsInt($row['reply_count']);
                    $this->assertTrue($row['parent_message_id'] === null || is_int($row['parent_message_id']));
                }
            }
        }
        $this->assertNoDisclosedValues($payload, $forbidden);
    }

    private function error(int $status): array
    {
        [$code, $message] = match ($status) {
            404 => ['NOT_FOUND', '対象が見つかりません'],
            405 => ['METHOD_NOT_ALLOWED', 'このHTTPメソッドは使用できません'],
            500 => ['INTERNAL_ERROR', '処理に失敗したため時間をおいて再度お試しください'],
        };

        return ['error' => ['code' => $code, 'message' => $message, 'details' => []]];
    }

    public function test_ac_7_1_and_7_4_channels_filter_before_paging_and_keep_the_public_ceiling(): void
    {
        $owner = User::factory()->create();
        $expected = [];
        for ($i = 0; $i < 23; $i++) {
            $this->channel(['is_private' => true, 'name' => '秘密'.$i]);
            $expected[] = $this->channel(['description' => $i === 0 ? '説明' : null])->id;
            $this->channel(['deleted_at' => now()]);
        }
        $until = end($expected);
        $this->channel(['is_private' => true]);
        for ($page = 1; $page <= 4; $page++) {
            $response = $this->get('/api/channels?page='.$page)->assertOk()
                ->assertHeader('Content-Type', 'application/json; charset=utf-8');
            $this->assertSuccessContract($response->json(), false, ['秘密', $owner->email, $owner->password_hash]);
            $this->assertSame(array_slice($expected, ($page - 1) * 10, 10), array_column($response->json('data'), 'id'));
            $response->assertJsonPath('meta', ['current_page' => $page, 'until_id' => $until, 'per_page' => 10, 'total' => 23, 'last_page' => 3])
                ->assertJsonPath('links', ['prev' => $page > 1 ? '/api/channels?page='.($page - 1).'&until_id='.$until : null,
                    'next' => $page < 3 ? '/api/channels?page='.($page + 1).'&until_id='.$until : null]);
        }
        $guest = $this->get('/api/channels')->json();
        $this->actingAs($owner)->get('/api/channels?search=秘密&sort=desc&user_id='.$owner->id)->assertExactJson($guest);
        $this->assertSame(['id', 'name', 'description'], array_keys($guest['data'][0]));
        $this->assertNull($guest['data'][1]['description']);
        $this->assertSame('説明', $guest['data'][0]['description']);
        $new = $this->channel(['created_at' => '2020-01-01 00:00:00']);
        $continued = $this->get('/api/channels?page=2&until_id='.$until)->assertJsonPath('meta.total', 23);
        $this->assertSame(array_slice($expected, 10, 10), array_column($continued->json('data'), 'id'));
        $this->get('/api/channels')->assertJsonPath('meta.total', 24)->assertJsonPath('meta.until_id', $new->id)->assertJsonPath('data.0.id', $new->id);
        $this->get('/api/channels?until_id=0')->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        $this->assertSuccessContract($this->get('/api/channels?until_id='.Channel::max('id'))->assertJsonPath('meta.total', 24)->json(), false, ['秘密']);
    }

    public function test_ac_7_2_messages_use_independent_pages_and_shared_ceiling_with_current_reply_counts(): void
    {
        User::factory()->create(['name' => '鈴木 花子']);
        $channel = $this->channel();
        $other = $this->channel(['is_private' => true]);
        $deletedParent = $this->message($channel, ['deleted_at' => now(), 'body' => '削除親の秘密']);
        $messages = [];
        $replies = [];
        for ($i = 0; $i < 12; $i++) {
            if ($i < 7) {
                $messages[] = $this->message($channel)->id;
            }
            $this->message($channel, ['deleted_at' => now(), 'body' => '削除投稿の秘密']);
            $replies[] = $this->message($channel, ['parent_message_id' => $i % 2 ? $messages[0] : $deletedParent->id, 'edited_at' => $i === 11 ? now() : null])->id;
            $this->message($channel, ['parent_message_id' => $messages[0], 'deleted_at' => now(), 'body' => '削除返信の秘密']);
            $this->message($other, ['body' => '非公開の秘密']);
        }
        $until = end($replies);
        $messages = array_reverse($messages);
        $replies = array_reverse($replies);
        for ($page = 1; $page <= 4; $page++) {
            $response = $this->get('/api/channels/'.$channel->id.'/messages?page='.$page)->assertOk()
                ->assertHeader('Content-Type', 'application/json; charset=utf-8');
            $this->assertSuccessContract($response->json(), true, ['秘密', User::first()->email, User::first()->password_hash]);
            $this->assertSame(array_slice($messages, ($page - 1) * 5, 5), array_column($response->json('data.messages'), 'id'));
            $this->assertSame(array_slice($replies, ($page - 1) * 5, 5), array_column($response->json('data.replies'), 'id'));
            $response->assertJsonPath('meta', ['current_page' => $page, 'until_id' => $until, 'per_type' => 5,
                'messages_total' => 7, 'replies_total' => 12, 'last_page' => 3])
                ->assertJsonPath('links', ['prev' => $page > 1 ? '/api/channels/'.$channel->id.'/messages?page='.($page - 1).'&until_id='.$until : null,
                    'next' => $page < 3 ? '/api/channels/'.$channel->id.'/messages?page='.($page + 1).'&until_id='.$until : null]);
        }
        $first = $this->get('/api/channels/'.$channel->id.'/messages')->json();
        $row = $first['data']['replies'][0];
        $this->assertSame(['id', 'parent_message_id', 'body', 'author_name', 'created_at', 'is_edited', 'reply_count'], array_keys($row));
        $this->assertSame('2026-08-17T09:12:00+09:00', $row['created_at']);
        $this->assertSame('鈴木 花子', $row['author_name']);
        $this->assertTrue($row['is_edited']);
        $this->assertSame(0, $row['reply_count']);
        $this->assertSame($deletedParent->id, $first['data']['replies'][1]['parent_message_id']);
        $this->assertNull($first['data']['messages'][0]['parent_message_id']);
        $this->assertFalse($first['data']['messages'][0]['is_edited']);
        $this->assertSame(' 公開本文\n<script> ', $row['body']);
        $this->actingAs(User::first())->get('/api/channels/'.$channel->id.'/messages')->assertExactJson($first);
        $new = $this->message($channel, ['parent_message_id' => $messages[6], 'created_at' => '2030-01-01 00:00:00']);
        $continued = $this->get('/api/channels/'.$channel->id.'/messages?page=2&until_id='.$until)
            ->assertJsonPath('meta.replies_total', 12)->assertJsonPath('data.messages.1.reply_count', 19);
        $this->assertSame(array_slice($replies, 5, 5), array_column($continued->json('data.replies'), 'id'));
        $this->assertSame(array_slice($messages, 5, 5), array_column($continued->json('data.messages'), 'id'));
        $this->get('/api/channels/'.$channel->id.'/messages')->assertJsonPath('meta.until_id', $new->id)
            ->assertJsonPath('meta.replies_total', 13)->assertJsonPath('data.replies.0.id', $new->id);
        $this->assertSuccessContract($this->get('/api/channels/'.$channel->id.'/messages?until_id='.Message::max('id'))
            ->assertJsonPath('meta.messages_total', 7)->assertJsonPath('meta.replies_total', 13)->json(), true, ['秘密']);
    }

    public function test_empty_and_one_sided_lists_and_out_of_range_pages_are_successful(): void
    {
        $this->get('/api/channels')->assertExactJson(['data' => [], 'meta' => ['current_page' => 1,
            'until_id' => 0, 'per_page' => 10, 'total' => 0, 'last_page' => 1], 'links' => ['prev' => null, 'next' => null]]);
        User::factory()->create();
        $channel = $this->channel();
        $url = '/api/channels/'.$channel->id.'/messages';
        $this->get($url)->assertExactJson(['data' => ['messages' => [], 'replies' => []], 'meta' => [
            'current_page' => 1, 'until_id' => 0, 'per_type' => 5, 'messages_total' => 0, 'replies_total' => 0, 'last_page' => 1],
            'links' => ['prev' => null, 'next' => null]]);
        $parent = $this->message($channel);
        $this->get($url)->assertJsonCount(1, 'data.messages')->assertJsonPath('data.replies', []);
        $parent->forceFill(['deleted_at' => now()])->save();
        for ($i = 0; $i < 6; $i++) {
            $this->message($channel, ['parent_message_id' => $parent->id]);
        }
        $this->get($url.'?page=2')->assertJsonPath('data.messages', [])->assertJsonCount(1, 'data.replies')->assertJsonPath('meta.last_page', 2);
        $this->get($url.'?page=3')->assertJsonPath('data', ['messages' => [], 'replies' => []]);
        $this->get($url.'?until_id=0')->assertJsonPath('data', ['messages' => [], 'replies' => []])->assertJsonPath('meta.last_page', 1);
    }

    public function test_creation_time_precedes_id_and_decimal_queries_allow_leading_zeroes(): void
    {
        User::factory()->create();
        $channel = $this->channel();
        $earlier = $this->channel(['created_at' => '2020-01-01 00:00:00']);
        $this->assertSame([$earlier->id, $channel->id], array_column($this->get('/api/channels?page=01')->json('data'), 'id'));
        $parent = $this->message($channel);
        $old = $this->message($channel, ['created_at' => '2020-01-01 00:00:00']);
        $reply = $this->message($channel, ['parent_message_id' => $parent->id]);
        $oldReply = $this->message($channel, ['parent_message_id' => $parent->id, 'created_at' => '2020-01-01 00:00:00']);
        $url = '/api/channels/'.$channel->id.'/messages';
        $response = $this->get($url.'?page=01&until_id=000'.$oldReply->id)->assertOk()
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.until_id', $oldReply->id);
        $this->assertSame([$parent->id, $old->id], array_column($response->json('data.messages'), 'id'));
        $this->assertSame([$reply->id, $oldReply->id], array_column($response->json('data.replies'), 'id'));
        foreach (['/api/channels', $url] as $path) {
            $this->get($path.'?page=01&until_id=00')->assertOk()->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.until_id', 0);
            $this->get($path.'?page='.PHP_INT_MAX)->assertOk()->assertJsonPath('meta.current_page', PHP_INT_MAX);
        }
    }

    public function test_mixed_creators_and_authors_are_public_for_guests_and_each_user_without_filters(): void
    {
        $users = [User::factory()->create(['name' => '佐藤 太郎']), User::factory()->create(['name' => '鈴木 花子']), User::factory()->create(['name' => '高橋 健'])];
        $channels = [];
        $expectedMessages = [];
        $expectedReplies = [];
        $forbidden = [];
        foreach ($users as $user) {
            $channels[] = $this->channel(['creator_id' => $user->id]);
            $private = $this->channel(['creator_id' => $user->id, 'is_private' => true, 'name' => '秘密の採用'.$user->id]);
            $private->members()->attach($user);
            $this->message($private, ['user_id' => $user->id, 'body' => '秘密の議事録']);
            $forbidden = [...$forbidden, $user->email, $user->password_hash, $private->name, '秘密の議事録'];
        }
        foreach ($users as $index => $user) {
            $parent = $this->message($channels[0], ['user_id' => $user->id, 'body' => '利用者'.$index.'の投稿', 'version' => 87]);
            $replyAuthor = $users[($index + 1) % 3];
            $reply = $this->message($channels[0], ['user_id' => $replyAuthor->id, 'parent_message_id' => $parent->id, 'body' => '利用者'.$index.'への返信']);
            $expectedMessages[] = ['id' => $parent->id, 'parent_message_id' => null, 'body' => $parent->body,
                'author_name' => $user->name, 'created_at' => '2026-08-17T09:12:00+09:00', 'is_edited' => false, 'reply_count' => 1];
            $expectedReplies[] = ['id' => $reply->id, 'parent_message_id' => $parent->id, 'body' => $reply->body,
                'author_name' => $replyAuthor->name, 'created_at' => '2026-08-17T09:12:00+09:00', 'is_edited' => false, 'reply_count' => 0];
            $otherParent = $this->message($channels[1], ['user_id' => $user->id, 'body' => '別公開チャンネルの本文']);
            $this->message($channels[2], ['parent_message_id' => null, 'body' => '別公開チャンネルの本文']);
            $this->message($channels[1], ['parent_message_id' => $otherParent->id, 'body' => '別公開チャンネルの返信']);
        }
        $expectedChannels = array_map(fn ($channel) => ['id' => $channel->id, 'name' => $channel->name, 'description' => null], $channels);
        foreach ([null, ...$users] as $viewer) {
            if ($viewer !== null) {
                $this->actingAs($viewer);
            }
            foreach (['', '?user_id='.$users[0]->id.'&author_id='.$users[1]->id.'&creator_id='.$users[2]->id.'&search=該当なし&sort=asc'] as $query) {
                $channelResponse = $this->get('/api/channels'.$query)->assertOk();
                $this->assertSame($expectedChannels, $channelResponse->json('data'));
                $this->assertSuccessContract($channelResponse->json(), false, $forbidden);
                $response = $this->get('/api/channels/'.$channels[0]->id.'/messages'.$query)->assertOk();
                $this->assertSame(['messages' => array_reverse($expectedMessages), 'replies' => array_reverse($expectedReplies)], $response->json('data'));
                $this->assertSuccessContract($response->json(), true, [...$forbidden, '別公開チャンネルの本文', '別公開チャンネルの返信']);
            }
        }
    }

    public function test_message_dominant_pages_use_the_message_ceiling_and_exclude_new_posts_on_continuation(): void
    {
        User::factory()->create();
        $channel = $this->channel();
        $other = $this->channel();
        $messages = [];
        $replies = [];
        for ($i = 0; $i < 12; $i++) {
            $messages[] = $this->message($channel)->id;
            if ($i < 2) {
                $replies[] = $this->message($channel, ['parent_message_id' => $messages[0]])->id;
            }
            $this->message($channel, ['deleted_at' => now(), 'body' => '個別削除の秘密']);
            $this->message($other, ['body' => '別公開の秘密']);
        }
        $until = end($messages);
        $this->message($channel, ['parent_message_id' => $messages[0], 'deleted_at' => now(), 'body' => '削除返信の秘密']);
        $this->message($other, ['body' => '別公開の秘密']);
        $messages = array_reverse($messages);
        $replies = array_reverse($replies);
        $url = '/api/channels/'.$channel->id.'/messages';
        for ($page = 1; $page <= 4; $page++) {
            $response = $this->get($url.'?page='.$page)->assertOk();
            $this->assertSame(array_slice($messages, ($page - 1) * 5, 5), array_column($response->json('data.messages'), 'id'));
            $this->assertSame(array_slice($replies, ($page - 1) * 5, 5), array_column($response->json('data.replies'), 'id'));
            $response->assertJsonPath('meta', ['current_page' => $page, 'until_id' => $until, 'per_type' => 5,
                'messages_total' => 12, 'replies_total' => 2, 'last_page' => 3])
                ->assertJsonPath('links', ['prev' => $page > 1 ? $url.'?page='.($page - 1).'&until_id='.$until : null,
                    'next' => $page < 3 ? $url.'?page='.($page + 1).'&until_id='.$until : null]);
            $this->assertSuccessContract($response->json(), true, ['秘密']);
        }
        $new = $this->message($channel, ['created_at' => '2030-01-01 00:00:00']);
        $continued = $this->get($url.'?page=2&until_id='.$until)->assertOk()
            ->assertJsonPath('meta.messages_total', 12)->assertJsonPath('meta.replies_total', 2)->assertJsonPath('meta.until_id', $until);
        $this->assertSame(array_slice($messages, 5, 5), array_column($continued->json('data.messages'), 'id'));
        $this->assertSame([], $continued->json('data.replies'));
        $fresh = $this->get($url)->assertOk()->assertJsonPath('meta.until_id', $new->id)
            ->assertJsonPath('meta.messages_total', 13)->assertJsonPath('meta.replies_total', 2);
        $this->assertSame([$new->id, ...array_slice($messages, 0, 4)], array_column($fresh->json('data.messages'), 'id'));
        $this->assertSame($replies, array_column($fresh->json('data.replies'), 'id'));
    }

    public function test_http_message_deletions_hide_parents_keep_live_replies_and_preserve_reply_counts(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel();
        $parent = $this->message($channel, ['body' => '削除される親の秘密', 'edited_at' => '2026-08-18 00:00:00', 'version' => 4]);
        $survivor = $this->message($channel);
        $liveReply = $this->message($channel, ['parent_message_id' => $parent->id]);
        $deletedReply = $this->message($channel, ['parent_message_id' => $survivor->id, 'body' => '削除される返信の秘密', 'version' => 3]);
        $otherReply = $this->message($channel, ['parent_message_id' => $survivor->id]);
        $url = '/api/channels/'.$channel->id.'/messages';
        $this->get($url)->assertJsonPath('meta.messages_total', 2)->assertJsonPath('meta.replies_total', 3)->assertJsonPath('data.messages.0.reply_count', 2);
        $snapshot = Message::orderBy('id')->get()->map->getAttributes()->all();
        $this->travelTo(now('UTC')->setDate(2026, 11, 10)->setTime(1, 2, 3));
        $this->actingAs($owner)->delete('/channels/'.$channel->id.'/messages/'.$parent->id)->assertStatus(303);
        $this->delete('/channels/'.$channel->id.'/messages/'.$deletedReply->id)->assertStatus(303);
        $response = $this->get($url)->assertOk()->assertJsonPath('meta.messages_total', 1)->assertJsonPath('meta.replies_total', 2)
            ->assertJsonPath('data.messages.0.reply_count', 2);
        $this->assertSame([$survivor->id], array_column($response->json('data.messages'), 'id'));
        $this->assertSame([$otherReply->id, $liveReply->id], array_column($response->json('data.replies'), 'id'));
        $this->assertSame($parent->id, $response->json('data.replies.1.parent_message_id'));
        $this->assertSuccessContract($response->json(), true, [$parent->body, $deletedReply->body]);
        $after = Message::orderBy('id')->get()->map->getAttributes()->all();
        foreach ($after as $index => $attributes) {
            if (in_array($attributes['id'], [$parent->id, $deletedReply->id], true)) {
                $this->assertSame('2026-11-10 01:02:03', $attributes['deleted_at']);
                $attributes['deleted_at'] = null;
            }
            $this->assertSame($snapshot[$index], $attributes);
        }
    }

    public function test_http_channel_deletion_removes_all_api_states_but_retains_channel_memberships_and_conversation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $channel = $this->channel(['name' => '終了するチャンネル', 'description' => '保持する説明']);
        $channel->members()->attach([$owner->id, $member->id]);
        $other = $this->channel();
        $parents = [];
        $replies = [];
        foreach ([[], ['edited_at' => '2026-08-18 00:00:00', 'version' => 2], ['deleted_at' => '2026-08-19 00:00:00', 'edited_at' => '2026-08-18 00:00:00', 'version' => 3]] as $state) {
            $parent = $this->message($channel, $state + ['body' => '保持する親'.($state['version'] ?? 1)]);
            $parents[] = $parent;
            $replies[] = $this->message($channel, $state + ['parent_message_id' => $parent->id, 'body' => '保持する返信'.($state['version'] ?? 1)]);
        }
        $url = '/api/channels/'.$channel->id.'/messages';
        $initial = $this->get($url)->assertOk()->assertJsonPath('meta.messages_total', 2)->assertJsonPath('meta.replies_total', 2);
        $this->assertSame([$parents[1]->id, $parents[0]->id], array_column($initial->json('data.messages'), 'id'));
        $this->assertSame([$replies[1]->id, $replies[0]->id], array_column($initial->json('data.replies'), 'id'));
        $this->assertSuccessContract($initial->json(), true, [$parents[2]->body, $replies[2]->body]);
        $this->assertSame([$channel->id, $other->id], array_column($this->get('/api/channels')->json('data'), 'id'));
        $channelBefore = $channel->fresh()->getAttributes();
        $membersBefore = DB::table('channel_user')->orderBy('channel_id')->orderBy('user_id')->get()->map(fn ($row) => (array) $row)->all();
        $messagesBefore = Message::orderBy('id')->get()->map->getAttributes()->all();
        $this->travelTo(now('UTC')->setDate(2026, 11, 10)->setTime(2, 3, 4));
        $this->actingAs($owner)->delete('/channels/'.$channel->id, ['confirmation_name' => $channel->name])->assertStatus(303)->assertRedirect('/channels');
        $listing = $this->get('/api/channels')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame([$other->id], array_column($listing->json('data'), 'id'));
        $this->assertSuccessContract($listing->json(), false, [$channel->name, $channel->description, '保持する親', '保持する返信']);
        foreach (['', '?page=0&until_id[]=1'] as $query) {
            $this->get($url.$query)->assertNotFound()->assertExactJson($this->error(404))->assertSee('"details":{}', false);
        }
        $channelAfter = $channel->fresh()->getAttributes();
        $this->assertSame('2026-11-10 02:03:04', $channelAfter['deleted_at']);
        $this->assertNull($channelAfter['active_name']);
        // active_nameは削除状態から生成されるDB列であり、保存する名前・説明等とは分けます。
        unset($channelBefore['active_name'], $channelAfter['active_name']);
        $channelAfter['deleted_at'] = null;
        $this->assertSame($channelBefore, $channelAfter);
        $this->assertSame($membersBefore, DB::table('channel_user')->orderBy('channel_id')->orderBy('user_id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($messagesBefore, Message::orderBy('id')->get()->map->getAttributes()->all());
    }

    public static function invalidQueries(): array
    {
        $cases = [];
        foreach (['page' => ['0', '-1', '', '1.5', 'a', [], ['1'], '+1', '1e2', ' 1 ', '99999999999999999999999999999'],
            'until_id' => ['-1', '', '1.5', 'a', [], ['1'], '+1', '1e2', ' 1 ', '99999999999999999999999999999']] as $key => $values) {
            foreach ($values as $index => $value) {
                $cases[$key.' '.$index] = [$key, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_queries_are_field_specific_json_errors(string $key, mixed $value): void
    {
        User::factory()->create();
        $channel = $this->channel();
        foreach (['/api/channels', '/api/channels/'.$channel->id.'/messages'] as $url) {
            $query = is_array($value) ? $key.'[]=1' : http_build_query([$key => $value]);
            $this->get($url.'?'.$query, ['Accept' => 'text/html'])->assertStatus(422)
                ->assertHeader('Content-Type', 'application/json; charset=utf-8')->assertHeaderMissing('Location')
                ->assertExactJson(['error' => ['code' => 'VALIDATION_ERROR', 'message' => '入力内容を確認してください',
                    'details' => [$key => [$key.($key === 'page' ? 'は1以上の整数で指定してください' : 'は0以上の整数で指定してください')]]]]);
        }
    }

    public function test_ac_7_3_non_public_and_invalid_targets_are_identical_404_before_validation(): void
    {
        $owner = User::factory()->create();
        $private = $this->channel(['is_private' => true, 'name' => '採用の秘密']);
        $deleted = $this->channel(['deleted_at' => now()]);
        $this->message($private, ['body' => '非公開本文']);
        $this->message($deleted, ['body' => '終了本文']);
        foreach ([$private->id, $deleted->id, '999999', '0', '-1', 'abc', '1.2', $private->name, '999999999999999999999999999'] as $id) {
            foreach (['', '?page=0&until_id[]=1'] as $query) {
                $this->get('/api/channels/'.rawurlencode($id).'/messages'.$query)->assertNotFound()->assertExactJson($this->error(404))->assertSee('"details":{}', false);
            }
        }
        $this->actingAs($owner)->get('/api/channels/'.$private->id.'/messages?page=0')->assertNotFound()->assertExactJson($this->error(404))->assertSee('"details":{}', false);
    }

    public function test_http_methods_head_cors_and_unknown_paths_follow_the_json_contract_without_writes(): void
    {
        User::factory()->create();
        $channel = $this->channel();
        $message = $this->message($channel);
        $before = [$channel->fresh()->getAttributes(), $message->fresh()->getAttributes()];
        foreach (['/api/channels', '/api/channels/'.$channel->id.'/messages'] as $url) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
                $this->call($method, $url, ['body' => '変更本文'], [], [], ['HTTP_ACCEPT' => 'text/html',
                    'HTTP_ORIGIN' => 'https://portal.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])
                    ->assertStatus(405)->assertHeader('Allow', 'GET, HEAD')->assertExactJson($this->error(405))->assertSee('"details":{}', false)
                    ->assertHeader('Content-Type', 'application/json; charset=utf-8');
            }
            $headers = ['Origin' => 'https://portal.example'];
            $get = $this->get($url, $headers)->assertHeader('Access-Control-Allow-Origin', '*');
            $head = $this->head($url, $headers)->assertOk()->assertContent('');
            $this->assertSame($get->headers->all(), $head->headers->all());
        }
        foreach (['/api', '/api/missing', '/api/user', '/api/channels/1', '/api/channels/1/messages/1'] as $url) {
            foreach (['GET', 'POST', 'OPTIONS'] as $method) {
                $this->call($method, $url, [], [], [], ['HTTP_ORIGIN' => 'https://portal.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])
                    ->assertNotFound()->assertHeader('Content-Type', 'application/json; charset=utf-8')->assertExactJson($this->error(404))->assertSee('"details":{}', false);
            }
        }
        foreach (['/api/missing' => 404, '/api/channels?page=0' => 422, '/api/channels/none/messages' => 404] as $url => $status) {
            $get = $this->get($url);
            $head = $this->head($url)->assertStatus($status)->assertContent('');
            $this->assertSame($get->headers->all(), $head->headers->all());
        }
        $this->assertSame($before, [$channel->fresh()->getAttributes(), $message->fresh()->getAttributes()]);
        $this->assertDatabaseCount('channels', 1);
        $this->assertDatabaseCount('messages', 1);
        for ($i = 0; $i < 61; $i++) {
            $this->get('/api/channels')->assertOk()->assertHeaderMissing('X-RateLimit-Limit');
        }
    }

    private function withMethodParameterOverride(callable $check): void
    {
        $previous = SymfonyRequest::getHttpMethodParameterOverride();
        SymfonyRequest::enableHttpMethodParameterOverride();
        try {
            $check();
        } finally {
            // Symfonyには無効化APIがないため、public/index.phpで有効になる静的状態を復元します。
            (new ReflectionProperty(SymfonyRequest::class, 'httpMethodParameterOverride'))->setValue(null, $previous);
        }
        $this->assertSame($previous, SymfonyRequest::getHttpMethodParameterOverride());
    }

    public static function methodOverrides(): array
    {
        return [
            'body GET' => ['body', 'GET'], 'body HEAD' => ['body', 'HEAD'],
            'query GET' => ['query', 'GET'], 'query HEAD' => ['query', 'HEAD'],
            'header GET' => ['header', 'GET'], 'header HEAD' => ['header', 'HEAD'],
        ];
    }

    #[DataProvider('methodOverrides')]
    public function test_post_method_overrides_cannot_bypass_api_methods_or_remove_error_bodies(string $source, string $method): void
    {
        User::factory()->create();
        $channel = $this->channel();
        $this->message($channel);
        $snapshot = [Channel::orderBy('id')->get()->map->getAttributes()->all(), Message::orderBy('id')->get()->map->getAttributes()->all()];
        $this->withMethodParameterOverride(function () use ($source, $method, $channel, $snapshot) {
            foreach (['/api/channels' => 405, '/api/channels/'.$channel->id.'/messages' => 405, '/api/missing' => 404] as $url => $status) {
                $body = ['body' => '変更できない本文'];
                $headers = ['HTTP_ACCEPT' => 'text/html'];
                if ($source === 'body') {
                    $body['_method'] = $method;
                } elseif ($source === 'query') {
                    $url .= '?_method='.$method;
                } else {
                    $headers['HTTP_X_HTTP_METHOD_OVERRIDE'] = $method;
                }
                $response = $this->call('POST', $url, $body, [], [], $headers)->assertStatus($status)
                    ->assertHeader('Content-Type', 'application/json; charset=utf-8')
                    ->assertExactJson($this->error($status))->assertSee('"details":{}', false);
                if ($status === 405) {
                    $response->assertHeader('Allow', 'GET, HEAD');
                }
                $this->assertNotSame('', $response->getContent());
                $this->assertSame($snapshot, [Channel::orderBy('id')->get()->map->getAttributes()->all(), Message::orderBy('id')->get()->map->getAttributes()->all()]);
            }
        });
    }

    public function test_web_post_form_overrides_still_update_and_delete_channels(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel();
        $this->actingAs($owner);
        $this->withMethodParameterOverride(function () use ($channel) {
            $this->post('/channels/'.$channel->id, ['_method' => 'PATCH', 'name' => 'フォームで変更', 'description' => '変更した説明'])
                ->assertStatus(303)->assertRedirect('/channels/'.$channel->id);
            $this->assertSame('フォームで変更', $channel->fresh()->name);
            $this->assertSame('変更した説明', $channel->fresh()->description);
            $this->post('/channels/'.$channel->id, ['_method' => 'DELETE', 'confirmation_name' => 'フォームで変更'])
                ->assertStatus(303)->assertRedirect('/channels');
            $this->assertNotNull($channel->fresh()->deleted_at);
        });
    }

    public function test_db_failures_on_the_public_endpoint_are_redacted_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        User::factory()->create();
        $channel = $this->channel();
        $this->message($channel);
        Message::retrieved(function () {
            throw new QueryException('mysql', 'select private_sql ?', ['secret-body', 'secret-password'], new RuntimeException('private details'));
        });
        Log::spy();
        $this->get('/api/channels/'.$channel->id.'/messages')->assertStatus(500)
            ->assertHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertExactJson($this->error(500))->assertSee('"details":{}', false);
        Log::shouldHaveReceived('error')->once()->with('Application operation failed', [
            'exception_class' => QueryException::class, 'route' => 'api.messages.index', 'channel_id' => $channel->id,
        ]);
    }

    public function test_unexpected_failures_use_safe_json_and_redacted_logs_even_for_head(): void
    {
        User::factory()->create();
        $channel = $this->channel();
        Route::get('/api/failure/{channel}', function () {
            throw new RuntimeException('SQL password=secret 本文の秘密');
        })->name('api.failure');
        Log::spy();
        $url = '/api/failure/'.$channel->id;
        $get = $this->get($url)->assertStatus(500)->assertExactJson($this->error(500))->assertSee('"details":{}', false);
        $head = $this->head($url)->assertStatus(500)->assertContent('');
        $this->assertSame($get->headers->all(), $head->headers->all());
        Log::shouldHaveReceived('error')->twice()->with('Application operation failed', [
            'exception_class' => RuntimeException::class, 'route' => 'api.failure', 'channel_id' => $channel->id,
        ]);
    }
}
