<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Actions\Messages\SaveMessage;
use App\Exceptions\MessageConflict;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private function channel(User $owner, bool $private = false, string $name = '開発'): Channel
    {
        return app(SaveChannel::class)->create($owner, ['name' => $name, 'is_private' => $private ? '1' : '0']);
    }

    private function message(Channel $channel, User $author, array $values = []): Message
    {
        $message = new Message;
        $message->forceFill(array_replace([
            'channel_id' => $channel->id, 'user_id' => $author->id, 'body' => '保存された本文',
            'parent_message_id' => null, 'version' => 1, 'created_at' => '2026-10-28 15:30:00',
        ], $values))->save();

        return $message->refresh();
    }

    private function url(Channel $channel, ?Message $message = null): string
    {
        return '/channels/'.$channel->id.($message ? '/messages/'.$message->id : '');
    }

    private function assertTextareaValue($response, string $id, string $expected): void
    {
        preg_match('/<textarea\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/textarea>/s', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        // HTMLのtextarea先頭で除去される1改行を除いた、ブラウザーの初期値を確認します。
        $rendered = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_starts_with($rendered, "\n") ? substr($rendered, 1) : $rendered;
        $this->assertSame($expected, $value);
        $response->assertDontSee('<本文>', false);
    }

    public function test_ac_4_1_post_uses_the_authenticated_author_and_displays_name_jst_time_and_body(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create(['name' => '鈴木 花子']);
        $channel = $this->channel($owner, true);
        $channel->members()->attach($author);
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(0, 12));
        $this->actingAs($author)->post($this->url($channel).'/messages', [
            'body' => " 前後の空白と\n改行を保持する ", 'user_id' => $owner->id, 'channel_id' => 999,
            'parent_message_id' => 123, 'version' => 99, 'edited_at' => now(), 'deleted_at' => now(),
        ])->assertStatus(303)->assertRedirect($this->url($channel));
        $message = Message::sole();
        $this->assertSame($author->id, $message->user_id);
        $this->assertSame($channel->id, $message->channel_id);
        $this->assertNull($message->parent_message_id);
        $this->assertSame(1, $message->version);
        $this->assertNull($message->edited_at);
        $this->assertNull($message->deleted_at);
        $this->get($this->url($channel))->assertOk()->assertSee('鈴木 花子')->assertSee('2026/10/30 09:12')
            ->assertSee(" 前後の空白と\n改行を保持する ")->assertSee('1,000文字まで')->assertDontSee('<script', false);
    }

    public function test_ac_4_2_inline_edit_keeps_surrounding_conversation_and_updates_only_body_edit_time_and_version(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $first = $this->message($channel, $author, ['body' => '前の会話']);
        $message = $this->message($channel, $author);
        $last = $this->message($channel, $author, ['body' => '後の会話']);
        $query = '?messages_until='.$last->id.'&messages_pages=1';
        $this->actingAs($author)->get($this->url($channel).$query.'&edit='.$message->id)->assertOk()
            ->assertSeeInOrder(['前の会話', 'メッセージを編集', '後の会話'])->assertSee('やめる');
        $created = $message->created_at->toDateTimeString();
        $this->patch($this->url($channel, $message), ['body' => '変更後の本文', 'expected_version' => 1,
            'messages_until' => $last->id, 'messages_pages' => 1])->assertStatus(303)
            ->assertRedirect($this->url($channel).$query.'#message-'.$message->id);
        $saved = $message->fresh();
        $this->assertSame('変更後の本文', $saved->body);
        $this->assertSame(2, $saved->version);
        $this->assertNotNull($saved->edited_at);
        $this->assertNull($saved->deleted_at);
        $this->assertSame($created, $saved->created_at->toDateTimeString());
        $this->get($this->url($channel).$query)->assertOk()->assertSeeInOrder(['前の会話', '変更後の本文', '編集済み', '後の会話']);
    }

    public function test_ac_4_3_channel_creator_cannot_edit_or_delete_another_authors_message(): void
    {
        $sato = User::factory()->create(['name' => '佐藤 太郎']);
        $suzuki = User::factory()->create(['name' => '鈴木 花子']);
        $channel = $this->channel($sato);
        $message = $this->message($channel, $suzuki);
        $snapshot = $message->toArray();
        $this->actingAs($sato)->get($this->url($channel, $message).'/delete-confirmation')
            ->assertForbidden()->assertSee('この操作はできません')->assertDontSee($message->body);
        $this->get($this->url($channel).'?edit='.$message->id)->assertForbidden();
        $this->patch($this->url($channel, $message), ['body' => '', 'expected_version' => 1])->assertForbidden();
        $this->delete($this->url($channel, $message))->assertForbidden();
        $this->get($this->url($channel))->assertDontSee('aria-label="メッセージを編集"', false)
            ->assertDontSee('aria-label="メッセージを削除"', false);
        $this->assertSame($snapshot, $message->fresh()->toArray());
    }

    public function test_ac_4_4_confirmation_and_cancel_do_not_write_and_delete_preserves_position_author_dates_and_replies(): void
    {
        $author = User::factory()->create(['name' => '鈴木 花子']);
        $channel = $this->channel($author, true);
        $message = $this->message($channel, $author, ['body' => '画面から消す元本文', 'edited_at' => now(), 'version' => 3]);
        $reply = $this->message($channel, $author, ['body' => '返信の本文', 'parent_message_id' => $message->id]);
        $deletedReply = $this->message($channel, $author, ['parent_message_id' => $message->id, 'deleted_at' => now()]);
        $before = $message->toArray();
        $this->actingAs($author)->get($this->url($channel, $message).'/delete-confirmation?messages_until='.$message->id)
            ->assertOk()->assertSee($message->body)->assertSee('鈴木 花子')->assertSee('2026/10/29 00:30')
            ->assertSee('削除する')->assertSee('やめる')->assertSee('name="_token"', false);
        $this->get($this->url($channel))->assertOk();
        $this->assertSame($before, $message->fresh()->toArray());
        $replySnapshot = [$reply->toArray(), $deletedReply->toArray()];
        $this->delete($this->url($channel, $message), ['messages_until' => $message->id, 'messages_pages' => 1])
            ->assertStatus(303)->assertRedirect($this->url($channel).'?messages_until='.$message->id.'&messages_pages=1#message-'.$message->id);
        $after = $message->fresh();
        $this->assertNotNull($after->deleted_at);
        $this->assertSame($before, array_replace($after->toArray(), ['deleted_at' => null]));
        $this->assertSame($replySnapshot, [$reply->fresh()->toArray(), $deletedReply->fresh()->toArray()]);
        $this->get($this->url($channel))->assertOk()->assertSee('このメッセージは削除されました')
            ->assertSee('鈴木 花子')->assertSee('2026/10/29 00:30')->assertSee('返信2件')->assertSee('id="message-'.$message->id.'"', false)
            ->assertDontSee('画面から消す元本文')->assertDontSee('返信の本文')->assertDontSee('編集済み');
        foreach ([$this->url($channel).'?edit='.$message->id, $this->url($channel, $message).'/delete-confirmation'] as $url) {
            $this->get($url)->assertStatus(409)->assertDontSee('画面から消す元本文');
        }
        $this->patch($this->url($channel, $message), ['body' => '送信した旧本文', 'expected_version' => 3])
            ->assertStatus(409)->assertDontSee('送信した旧本文')->assertDontSee('画面から消す元本文');
        $this->delete($this->url($channel, $message))->assertStatus(409)->assertDontSee('画面から消す元本文');
    }

    public function test_history_keeps_the_id_boundary_and_read_position_and_orders_same_time_by_id(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $this->actingAs($author)->get($this->url($channel))->assertOk()->assertSee('メッセージがありません')->assertDontSee('以前のメッセージを表示');
        $messages = collect(range(1, 11))->map(fn ($i) => $this->message($channel, $author, ['body' => '通常本文'.$i]));
        $reply = $this->message($channel, $author, ['body' => '混ざらない返信', 'parent_message_id' => $messages[0]->id]);
        $response = $this->get($this->url($channel))->assertOk()->assertDontSee('通常本文1<', false)->assertDontSee($reply->body)
            ->assertSeeInOrder(['通常本文2', '通常本文3', '通常本文11']);
        $boundary = $messages->last()->id;
        preg_match('/class="message-history"><a href="([^"]+)"/', $response->getContent(), $historyLink);
        $historyUrl = html_entity_decode($historyLink[1]);
        parse_str(parse_url($historyUrl, PHP_URL_QUERY), $historyQuery);
        $this->assertSame((string) $boundary, $historyQuery['messages_until']);
        $this->assertSame('2', $historyQuery['messages_pages']);
        $this->assertSame('message-'.$messages[1]->id, parse_url($historyUrl, PHP_URL_FRAGMENT));
        $this->message($channel, $author, ['body' => 'あとから追加した投稿']);
        $this->get($this->url($channel).'?messages_until='.$boundary.'&messages_pages=2')->assertOk()
            ->assertSeeInOrder(['通常本文1', '通常本文2', '通常本文11'])->assertDontSee('あとから追加した投稿')->assertDontSee('以前のメッセージを表示');
        // 対象を上限にするので検索結果から古い投稿へも到達できます。
        $this->get($this->url($channel).'?focus='.$messages[0]->id)->assertOk()->assertSee('通常本文1')->assertDontSee('通常本文2');
        $this->get($this->url($channel))->assertSee('あとから追加した投稿');
    }

    public function test_ten_posts_have_no_older_link_and_cursor_uses_timestamp_then_id_rather_than_maximum_id(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $newest = $this->message($channel, $author, ['body' => '日時が最新', 'created_at' => '2026-10-30 00:00:00']);
        foreach (range(1, 9) as $i) {
            $this->message($channel, $author, ['body' => '古い投稿'.$i, 'created_at' => '2026-10-29 00:00:00']);
        }
        $this->actingAs($author)->get($this->url($channel))->assertOk()->assertDontSee('以前のメッセージを表示')
            ->assertSee('name="messages_until" value="'.$newest->id.'"', false)->assertSeeInOrder(['古い投稿1', '古い投稿9', '日時が最新']);
        $this->get($this->url($channel).'?messages_pages='.PHP_INT_MAX)->assertOk()->assertSee('日時が最新');
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_post_and_edit_keep_input_in_the_correct_form_and_never_write(mixed $body, string $error): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $message = $this->message($channel, $author);
        $range = ['messages_until' => $message->id, 'messages_pages' => 2];
        $this->actingAs($author)->from('https://example.com/external')->post($this->url($channel).'/messages', $range + ['body' => $body])
            ->assertStatus(303)->assertRedirect($this->url($channel).'?messages_until='.$message->id.'&messages_pages=2')->assertSessionHasErrors(['body' => $error]);
        $response = $this->get($this->url($channel).'?messages_until='.$message->id.'&messages_pages=2&edit='.$message->id)->assertOk()->assertSee($error);
        $this->assertTextareaValue($response, 'edit-body', $message->body);
        $this->patch($this->url($channel, $message), $range + ['body' => $body, 'expected_version' => 1])->assertStatus(303)->assertSessionHasErrors(['body' => $error]);
        $page = $this->get($this->url($channel).'?messages_until='.$message->id.'&messages_pages=2&edit='.$message->id)->assertOk()->assertSee($error);
        $page->assertSee('name="expected_version" value="1"', false);
        if ($body === '') {
            $this->assertTextareaValue($page, 'edit-body', '');
        }
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame(1, $message->fresh()->version);
    }

    public static function invalidBodies(): array
    {
        return [
            ['', 'メッセージを入力してください'],
            [" \t\n　", 'メッセージを入力してください'],
            [['不正な型'], 'メッセージを入力してください'],
            [str_repeat('あ', 1001), 'メッセージは1,000文字以内で入力してください'],
        ];
    }

    public function test_thousand_japanese_characters_are_accepted_for_post_and_edit(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $this->actingAs($author)->post($this->url($channel).'/messages', ['body' => str_repeat('あ', 1000)])->assertStatus(303)->assertSessionHasNoErrors();
        $message = Message::sole();
        $this->patch($this->url($channel, $message), ['body' => str_repeat('い', 1000), 'expected_version' => 1])->assertStatus(303)->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('い', 1000), $message->fresh()->body);
    }

    public function test_stale_edit_is_409_with_current_and_draft_and_rechecks_the_version_on_every_confirmation(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $message = $this->message($channel, $author);
        $other = $this->message($channel, $author, ['body' => '周囲の会話']);
        $this->actingAs($author)->patch($this->url($channel, $message), ['body' => '先に保存した本文', 'expected_version' => 1])->assertStatus(303);
        $this->patch($this->url($channel, $message), ['body' => '自分の下書き', 'expected_version' => 1])->assertStatus(409)
            ->assertSee('別の画面で編集されています 内容を確認して保存してください')->assertSee('先に保存した本文')
            ->assertSee('自分の入力')->assertSee('自分の下書き')->assertSee('確認した内容で保存')->assertSee('周囲の会話')
            ->assertSee('name="expected_version" value="2"', false);
        $this->assertSame('先に保存した本文', $message->fresh()->body);
        $this->get($this->url($channel))->assertOk();
        $this->assertSame(2, $message->fresh()->version);
        $this->patch($this->url($channel, $message), ['body' => '別画面の再変更', 'expected_version' => 2])->assertStatus(303);
        $this->patch($this->url($channel, $message), ['body' => '自分の下書き', 'expected_version' => 2])->assertStatus(409)
            ->assertSee('別画面の再変更')->assertSee('name="expected_version" value="3"', false);
        $this->patch($this->url($channel, $message), ['body' => '確認後の本文', 'expected_version' => 3])->assertStatus(303);
        $this->assertSame('確認後の本文', $message->fresh()->body);
        $this->assertSame(4, $message->fresh()->version);
    }

    public function test_validation_error_keeps_the_original_version_and_empty_body_instead_of_substituting_current_values(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $message = $this->message($channel, $author);
        $this->actingAs($author)->patch($this->url($channel, $message), ['body' => '別の画面で変更', 'expected_version' => 1])->assertStatus(303);
        $invalid = $this->patch($this->url($channel, $message), ['body' => '', 'expected_version' => 1])->assertStatus(303);
        $this->get($invalid->headers->get('Location'))->assertOk()->assertSee('name="expected_version" value="1"', false)
            ->assertDontSee('別の画面で変更</textarea>', false);
        $this->patch($this->url($channel, $message), ['body' => 'エラー修正後の入力', 'expected_version' => 1])->assertStatus(409)->assertSee('別の画面で変更');
        $invalid = $this->patch($this->url($channel, $message), ['body' => ['型違い'], 'expected_version' => ['型違い']])->assertStatus(303);
        $this->get($invalid->headers->get('Location'))->assertOk()->assertDontSee('型違い')->assertSee('name="expected_version" value=""', false);
        $this->assertSame(2, $message->fresh()->version);
    }

    public function test_old_input_is_not_reused_by_another_channel_message_or_operation(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $otherChannel = $this->channel($author, false, '別の部屋');
        $first = $this->message($channel, $author);
        $second = $this->message($channel, $author, ['body' => '別の投稿']);
        $draft = str_repeat('長', 1001);
        $this->actingAs($author)->patch($this->url($channel, $first), ['body' => $draft, 'expected_version' => 1])->assertStatus(303);
        $this->get($this->url($channel).'?edit='.$second->id)->assertOk()->assertDontSee($draft)->assertDontSee('メッセージは1,000文字以内で入力してください');
        $this->post($this->url($channel).'/messages', ['body' => $draft])->assertStatus(303);
        $this->get($this->url($otherChannel))->assertOk()->assertDontSee($draft)->assertDontSee('メッセージは1,000文字以内で入力してください');
    }

    public function test_hidden_channels_wrong_channel_ids_and_membership_loss_are_rejected_before_input_and_conflict(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $channel = $this->channel($owner, true);
        $channel->members()->attach($author);
        $message = $this->message($channel, $author, ['version' => 2, 'deleted_at' => now()]);
        $channel->members()->detach($author);
        $this->actingAs($author);
        foreach ([$this->url($channel), $this->url($channel).'?edit='.$message->id, $this->url($channel, $message).'/delete-confirmation'] as $url) {
            $this->get($url)->assertNotFound()->assertDontSee('保存された本文')->assertDontSee('このメッセージは削除されています');
        }
        $this->patch($this->url($channel, $message), ['body' => '秘密の下書き', 'expected_version' => 1])->assertNotFound()->assertDontSee('秘密の下書き');
        $this->delete($this->url($channel, $message))->assertNotFound();
        $this->post($this->url($channel).'/messages', ['body' => ''])->assertNotFound();
        $public = $this->channel($owner, false, '公開');
        $this->get($this->url($public, $message).'/delete-confirmation')->assertNotFound();
        $this->get($this->url($public).'?focus='.$message->id)->assertNotFound();
        $this->get($this->url($public).'?messages_until='.$message->id)->assertNotFound();
        $this->patch($this->url($public, $message), ['body' => '不正な対象', 'expected_version' => 1])->assertNotFound();
        $channel->members()->attach($author);
        $channel->forceFill(['deleted_at' => now()])->save();
        $this->patch($this->url($channel, $message), ['body' => '秘密の下書き', 'expected_version' => 1])->assertNotFound()->assertDontSee('秘密の下書き');
    }

    #[DataProvider('invalidRanges')]
    public function test_invalid_get_ranges_are_422_and_post_ranges_redirect_to_a_safe_internal_page(array $range): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $this->actingAs($author)->get($this->url($channel).'?'.http_build_query($range))->assertStatus(422)->assertSee('表示条件が正しくありません');
        $this->from('https://example.com/unsafe')->post($this->url($channel).'/messages', $range + ['body' => '保存しない本文', 'return_url' => 'https://example.com/unsafe'])
            ->assertStatus(303)->assertRedirect($this->url($channel));
        $this->assertDatabaseCount('messages', 0);
    }

    public static function invalidRanges(): array
    {
        return [[['messages_pages' => 0]], [['messages_pages' => '1.5']], [['messages_pages' => ['1']]],
            [['messages_until' => '']], [['messages_until' => -1]], [['origin' => 'external']], [['messages_pages' => '99999999999999999999999999']]];
    }

    public function test_guests_csrf_and_unprovided_methods_cannot_change_messages(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $message = $this->message($channel, $author);
        $this->post($this->url($channel).'/messages', ['body' => '追加しない'])->assertRedirect('/login');
        $this->patch($this->url($channel, $message), ['body' => '変更しない', 'expected_version' => 1])->assertRedirect('/login');
        $this->delete($this->url($channel, $message))->assertRedirect('/login');
        $this->get($this->url($channel, $message).'/delete-confirmation')->assertRedirect('/login');
        $this->actingAs($author)->put($this->url($channel, $message), ['body' => '変更しない'])->assertStatus(405);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post($this->url($channel).'/messages', ['body' => '追加しない'])->assertRedirect('/login');
        $this->post($this->url($channel, $message), ['_method' => 'DELETE'])->assertRedirect('/login');
        $this->post($this->url($channel, $message), ['_method' => 'PATCH', 'body' => '変更しない', 'expected_version' => 1])->assertRedirect('/login');
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame(1, $message->fresh()->version);
        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_shared_actions_handle_replies_competing_versions_and_parent_deletion_without_changing_other_rows(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $channel = $this->channel($owner, true);
        $channel->members()->attach($author);
        $parent = $this->message($channel, $owner);
        $reply = $this->message($channel, $author, ['parent_message_id' => $parent->id]);
        $action = app(SaveMessage::class);
        $action->update($author, $channel, $reply, '返信の編集', 1);
        try {
            $action->update($author, $channel, $reply, '未確認の上書き', 1);
            $this->fail('古い版が保存されました');
        } catch (MessageConflict $exception) {
            $this->assertSame('返信の編集', $exception->current->body);
            $this->assertSame(2, $exception->current->version);
        }
        $action->delete($owner, $channel, $parent);
        $action->update($author, $channel, $reply, '親削除後の返信編集', 2);
        $action->delete($author, $channel, $reply);
        $this->assertSame('親削除後の返信編集', $reply->fresh()->body);
        $this->assertSame($parent->id, $reply->fresh()->parent_message_id);
        $this->assertSame(3, $reply->fresh()->version);
        $this->assertNotNull($reply->fresh()->deleted_at);
        try {
            $action->update($author, $channel, $reply, '削除後の変更', 3);
            $this->fail('削除後に変更されました');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_shared_action_reloads_channel_membership_and_target_before_saving_stale_models(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $channel = $this->channel($owner, true);
        $channel->members()->attach($author);
        $message = $this->message($channel, $author);
        $action = app(SaveMessage::class);
        $channel->members()->detach($author);
        foreach (['create', 'update', 'delete'] as $operation) {
            try {
                match ($operation) {
                    'create' => $action->create($author, $channel, '無権限の投稿'),
                    'update' => $action->update($author, $channel, $message, '無権限の編集', 1),
                    'delete' => $action->delete($author, $channel, $message),
                };
                $this->fail('所属解除後に保存されました');
            } catch (AuthorizationException $exception) {
                $this->assertSame(404, $exception->status());
            }
        }
        $channel->members()->attach($author);
        app(SaveChannel::class)->delete($owner, $channel, $channel->name);
        try {
            $action->update($author, $channel, $message, '終了後の編集', 1);
            $this->fail('チャンネル削除後に保存されました');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }
        $this->assertSame(1, $message->fresh()->version);
        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_failure_during_save_rolls_back_and_logs_ids_without_message_body_or_sql(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $message = $this->message($channel, $author);
        Log::spy();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'update `messages`')) {
                throw new RuntimeException('secret SQL and body');
            }
        });
        $this->actingAs($author)->patch($this->url($channel, $message), ['body' => '秘密の変更', 'expected_version' => 1])
            ->assertStatus(500)->assertDontSee('秘密の変更')->assertDontSee('secret SQL and body');
        $this->assertSame('保存された本文', $message->fresh()->body);
        $this->assertSame(1, $message->fresh()->version);
        $this->assertNull($message->fresh()->edited_at);
        Log::shouldHaveReceived('error')->with('Application operation failed', [
            'exception_class' => RuntimeException::class, 'route' => 'messages.update', 'channel_id' => $channel->id, 'message_id' => $message->id,
        ])->once();
    }

    public function test_post_response_failure_keeps_the_committed_message_and_does_not_resubmit(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $this->app->instance('fail-message-response', new class
        {
            public function handle($request, \Closure $next)
            {
                $next($request);
                throw new RuntimeException('response failed after commit');
            }
        });
        Route::getRoutes()->getByName('messages.store')->middleware('fail-message-response');
        $this->actingAs($author)->post($this->url($channel).'/messages', ['body' => '保存済みの投稿'])
            ->assertStatus(500)->assertSee('画面を開き直して結果を確認してください')->assertDontSee('http-equiv="refresh"', false);
        $this->assertDatabaseCount('messages', 1);
        $this->get($this->url($channel))->assertOk()->assertSee('保存済みの投稿');
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_seeding_creates_distributed_conversations_once_and_does_not_overwrite_edits_or_memberships(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('messages', 34);
        foreach (Channel::all() as $channel) {
            $this->assertSame($channel->is_private ? 4 : 10, $channel->messages()->count());
            $this->assertGreaterThanOrEqual(2, $channel->messages()->distinct()->count('user_id'));
            $this->assertGreaterThanOrEqual(2, $channel->messages()->distinct()->count('created_at'));
        }
        $this->assertSame(0, Message::whereNotNull('parent_message_id')->count());
        $this->assertSame(0, Message::where('version', '!=', 1)->count());
        $this->assertGreaterThanOrEqual(1, Message::whereNotNull('edited_at')->count());
        $this->assertGreaterThanOrEqual(1, Message::whereNotNull('deleted_at')->count());
        $message = Message::first();
        app(SaveMessage::class)->update($message->author, $message->channel, $message, '利用者の編集を維持', 1);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
    }

    public function test_views_escape_body_in_conversation_editor_conflict_and_confirmation_without_javascript(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $body = '<script>alert("xss")</script>';
        $message = $this->message($channel, $author, ['body' => $body]);
        $this->actingAs($author);
        foreach ([$this->url($channel), $this->url($channel).'?edit='.$message->id, $this->url($channel, $message).'/delete-confirmation'] as $url) {
            $this->get($url)->assertOk()->assertSee($body)->assertDontSee('<script', false);
        }
        $this->patch($this->url($channel, $message), ['body' => '先行編集', 'expected_version' => 1])->assertStatus(303);
        $this->patch($this->url($channel, $message), ['body' => $body, 'expected_version' => 1])->assertStatus(409)->assertSee($body)->assertDontSee('<script', false);
    }

    public function test_an_edit_outside_the_displayed_range_is_rejected_with_visible_feedback_and_no_save(): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $messages = collect(range(1, 11))->map(fn ($i) => $this->message($channel, $author, ['body' => '会話'.$i]));
        $target = $messages->first();
        $this->actingAs($author)->get($this->url($channel).'?edit='.$target->id)->assertStatus(422)->assertSee('表示条件が正しくありません');
        $response = $this->patch($this->url($channel, $target), ['body' => '保存してはいけない入力', 'expected_version' => 1])->assertStatus(303);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('表示条件が正しくありません')->assertDontSee('保存してはいけない入力');
        $this->assertSame('会話1', $target->fresh()->body);
        $this->assertSame(1, $target->fresh()->version);
    }

    #[DataProvider('changesAfterConflict')]
    public function test_conflict_render_rechecks_changes_after_the_save_transaction(string $change, int $status): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $channel = $this->channel($owner, true);
        $channel->members()->attach($author);
        $message = $this->message($channel, $author, ['body' => '隠すべき保存済み本文', 'version' => 2]);
        $this->app->bind(SaveMessage::class, fn () => new class($change) extends SaveMessage
        {
            public function __construct(private string $change) {}

            public function update(User $user, Channel $channel, Message $message, string $body, int $expectedVersion): Message
            {
                // 保存側と同じく、競合例外には当時のチャンネルを関連付けたモデルを持たせます。
                $snapshot = $message->fresh();
                $snapshot->setRelation('channel', $channel->fresh());
                match ($this->change) {
                    'channel_deleted' => DB::table('channels')->where('id', $channel->id)->update(['deleted_at' => now()]),
                    'message_deleted' => DB::table('messages')->where('id', $message->id)->update(['deleted_at' => now()]),
                    'membership_removed' => $channel->members()->detach($user->id),
                    'edited_again' => DB::table('messages')->where('id', $message->id)->update(['body' => '再表示直前の最新本文', 'version' => 3, 'edited_at' => now()]),
                };
                throw new MessageConflict($snapshot);
            }
        });
        $response = $this->actingAs($author)->patch($this->url($channel, $message), ['body' => '自分の下書き', 'expected_version' => 1])->assertStatus($status);
        if ($change === 'edited_again') {
            $response->assertSee('再表示直前の最新本文')->assertSee('自分の下書き')
                ->assertSee('name="expected_version" value="3"', false)->assertDontSee('隠すべき保存済み本文');
        } else {
            $response->assertDontSee('隠すべき保存済み本文')->assertDontSee('自分の下書き');
        }
    }

    public static function changesAfterConflict(): array
    {
        return [['channel_deleted', 404], ['message_deleted', 409], ['membership_removed', 404], ['edited_again', 409]];
    }

    #[DataProvider('leadingNewlines')]
    public function test_textareas_preserve_leading_newlines_in_editor_old_input_and_conflict(string $prefix): void
    {
        $author = User::factory()->create();
        $channel = $this->channel($author);
        $body = $prefix.'<本文>&末尾';
        $message = $this->message($channel, $author, ['body' => $body]);
        $edit = $this->url($channel).'?edit='.$message->id;

        $this->assertTextareaValue($this->actingAs($author)->get($edit)->assertOk(), 'edit-body', $body);
        $this->patch($this->url($channel, $message), ['body' => $body, 'expected_version' => 1])->assertStatus(303);
        $this->assertSame($body, $message->fresh()->body);
        $oversized = $prefix.str_repeat('あ', 1001);
        $this->post($this->url($channel).'/messages', ['body' => $oversized])->assertStatus(303);
        $this->assertTextareaValue($this->get($this->url($channel))->assertOk(), 'message-body', $oversized);
        $invalid = $this->patch($this->url($channel, $message), ['body' => $oversized, 'expected_version' => 2])->assertStatus(303);
        $this->assertTextareaValue($this->get($invalid->headers->get('Location'))->assertOk(), 'edit-body', $oversized);
        $this->assertTextareaValue($this->patch($this->url($channel, $message), ['body' => $body, 'expected_version' => 1])->assertStatus(409), 'edit-body', $body);
    }

    public static function leadingNewlines(): array
    {
        return [[''], ["\n"], ["\n\n"]];
    }
}
