<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    private function channel(User $creator, array $attributes = []): Channel
    {
        return app(SaveChannel::class)->create($creator, array_replace([
            'name' => '開発', 'description' => '開発の相談', 'is_private' => '0',
        ], $attributes));
    }

    private function message(Channel $channel, User $author, array $attributes = []): Message
    {
        $message = new Message;
        $message->forceFill(array_replace([
            'channel_id' => $channel->id, 'user_id' => $author->id, 'body' => '保持する元本文',
            'parent_message_id' => null, 'version' => 1,
        ], $attributes))->save();

        return $message;
    }

    public function test_guests_are_redirected_without_disclosing_or_changing_channels(): void
    {
        $channel = $this->channel(User::factory()->create(), ['is_private' => '1']);
        foreach (['/channels', '/channels/create', "/channels/{$channel->id}", "/channels/{$channel->id}/edit"] as $url) {
            $this->get($url)->assertStatus(302)->assertRedirect('/login');
        }
        $this->post('/channels', ['name' => '別名'])->assertRedirect('/login');
        $this->patch("/channels/{$channel->id}", ['name' => '改名'])->assertRedirect('/login');
        $this->delete("/channels/{$channel->id}", ['confirmation_name' => $channel->name])->assertRedirect('/login');
        $this->assertDatabaseCount('channels', 1);
        $this->assertNull($channel->fresh()->deleted_at);
    }

    #[DataProvider('visibilityValues')]
    public function test_creation_registers_the_authenticated_creator_as_a_member_and_opens_the_channel(string $visibility): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $response = $this->actingAs($user)->post('/channels', [
            'name' => ' 開発 ', 'description' => '説明', 'is_private' => $visibility,
            'creator_id' => $other->id, 'deleted_at' => '2020-01-01', 'active_name' => '偽装',
        ])->assertStatus(303);
        $channel = Channel::sole();
        $response->assertRedirect('/channels/'.$channel->id);
        $this->assertSame($user->id, $channel->creator_id);
        $this->assertSame(' 開発 ', $channel->name);
        $this->assertSame($visibility === '1', $channel->is_private);
        $this->assertNull($channel->deleted_at);
        $this->assertSame([$user->id], $channel->members()->pluck('users.id')->all());
        $this->get('/channels/'.$channel->id)->assertOk()->assertSee(' 開発 ')->assertSee('説明');
        $this->assertArrayNotHasKey('active_name', $channel->toArray());
    }

    public static function visibilityValues(): array
    {
        return [['0'], ['1']];
    }

    public function test_visible_channels_are_in_creation_order_and_private_membership_controls_every_entry_point(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $public = $this->channel($owner, ['name' => '公開の部屋']);
        $private = $this->channel($owner, ['name' => '秘密の部屋', 'description' => '秘密の説明', 'is_private' => '1']);
        $private->members()->attach($member);
        $last = $this->channel($owner, ['name' => '最後の部屋']);
        $public->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
        $private->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
        $last->forceFill(['created_at' => '2026-01-02 00:00:00'])->save();
        $deleted = $this->channel($owner, ['name' => '削除済みの部屋']);
        $deleted->forceFill(['deleted_at' => now()])->save();

        $this->actingAs($member)->get('/channels')->assertOk()
            ->assertSeeInOrder(['公開の部屋', '秘密の部屋', '最後の部屋'])
            ->assertDontSee('削除済みの部屋')->assertSee($owner->name);
        $this->get("/channels/{$private->id}")->assertOk()->assertSee('秘密の説明')->assertDontSee('>編集<', false);
        $this->actingAs($outsider)->get('/channels')->assertOk()->assertDontSee('秘密の部屋')->assertDontSee('秘密の説明');
        foreach (['', '/edit', '/edit?confirm_delete=1'] as $suffix) {
            $hidden = $this->get("/channels/{$private->id}{$suffix}")->assertNotFound()->assertDontSee('秘密の部屋');
            $missing = $this->get('/channels/99999'.$suffix)->assertNotFound();
            $this->assertSame($missing->getContent(), $hidden->getContent());
        }
        $this->patch("/channels/{$private->id}", [])->assertNotFound();
        $this->delete("/channels/{$private->id}", [])->assertNotFound();
        $this->get("/channels/{$public->id}")->assertOk();
        $private->members()->detach($member);
        $this->actingAs($member)->get("/channels/{$private->id}")->assertNotFound();
    }

    public function test_only_the_creator_can_edit_or_delete_a_visible_channel_before_input_validation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        foreach (['0', '1'] as $private) {
            $channel = $this->channel($owner, ['name' => '部屋'.$private, 'is_private' => $private]);
            if ($private === '1') {
                $channel->members()->attach($member);
            }
            $this->actingAs($member)->get("/channels/{$channel->id}/edit")->assertForbidden()->assertSee('この操作はできません');
            $this->patch("/channels/{$channel->id}", [])->assertForbidden();
            $this->delete("/channels/{$channel->id}", [])->assertForbidden();
            $this->assertNull($channel->fresh()->deleted_at);
        }
    }

    public function test_edit_preserves_visibility_and_creator_ignores_untrusted_fields_and_allows_its_own_name(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner, ['is_private' => '1']);
        $this->actingAs($owner)->patch("/channels/{$channel->id}", [
            'name' => $channel->name, 'description' => str_repeat('あ', 200),
            'is_private' => '0', 'creator_id' => 999, 'deleted_at' => '2020-01-01',
        ])->assertStatus(303)->assertRedirect("/channels/{$channel->id}");
        $this->assertTrue($channel->fresh()->is_private);
        $this->assertSame($owner->id, $channel->fresh()->creator_id);
        $this->assertNull($channel->fresh()->deleted_at);
        $this->patch("/channels/{$channel->id}", ['name' => '変更後', 'description' => '変更した説明'])->assertStatus(303);
        $this->get("/channels/{$channel->id}")->assertSee('変更後')->assertSee('変更した説明')->assertSee('プライベート');
    }

    #[DataProvider('invalidCreationInputs')]
    public function test_invalid_creation_returns_303_with_the_correct_error_and_does_not_create_rows(array $input, string $field, string $message): void
    {
        $this->actingAs(User::factory()->create())->post('/channels', array_replace([
            'name' => '開発', 'description' => '再入力する説明', 'is_private' => '1',
        ], $input))->assertStatus(303)->assertRedirect('/channels/create')->assertSessionHasErrors([$field => $message]);
        $this->get('/channels/create')->assertOk()->assertSee($message)
            ->assertSee($input['description'] ?? '再入力する説明');
        $this->assertDatabaseCount('channels', 0);
        $this->assertDatabaseCount('channel_user', 0);
    }

    public static function invalidCreationInputs(): array
    {
        return [
            [['name' => ''], 'name', 'チャンネル名を入力してください'],
            [['name' => " \t　\n"], 'name', 'チャンネル名を入力してください'],
            [['name' => str_repeat('あ', 51)], 'name', 'チャンネル名は50文字以内で入力してください'],
            [['name' => ['不正な型']], 'name', 'チャンネル名を入力してください'],
            [['description' => str_repeat('あ', 201)], 'description', '説明は200文字以内で入力してください'],
            [['is_private' => null], 'is_private', '公開範囲を選択してください'],
            [['is_private' => 'public'], 'is_private', '公開範囲を選択してください'],
            [['is_private' => ['1']], 'is_private', '公開範囲を選択してください'],
        ];
    }

    public function test_name_boundaries_and_exact_comparison_are_shared_by_validation_and_the_database(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        foreach (['開発', '開発 ', ' 開発', 'dev', 'DEV', 'ｄｅｖ', 'é', "e\u{0301}", str_repeat('あ', 50)] as $name) {
            $this->post('/channels', ['name' => $name, 'is_private' => '0'])->assertStatus(303)->assertSessionHasNoErrors();
            $this->assertDatabaseHas('channels', ['name' => $name, 'description' => null]);
        }
        $this->assertDatabaseCount('channels', 9);
        $private = $this->channel($owner, ['name' => '使用中の秘密名', 'is_private' => '1']);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post('/channels', ['name' => $private->name, 'is_private' => '0'])
            ->assertStatus(303)->assertSessionHasErrors(['name' => '使用できない名前です']);
        $this->get('/channels/create')->assertSee('使用できない名前です')->assertDontSee('秘密の説明')->assertDontSee('重複');
        $other = $this->channel($outsider, ['name' => '別名']);
        $this->patch("/channels/{$other->id}", ['name' => $private->name])->assertStatus(303)
            ->assertSessionHasErrors(['name' => '使用できない名前です']);
        $this->assertSame('別名', $other->fresh()->name);
    }

    public function test_a_name_collision_after_validation_is_mapped_to_the_same_input_error(): void
    {
        $owner = User::factory()->create();
        $inserted = false;
        DB::listen(function (QueryExecuted $query) use (&$inserted, $owner) {
            // 検証の重複SELECTが成功した直後に、別の作成が先に保存された状況を再現します。
            if (! $inserted && str_contains($query->sql, 'count(*)') && str_contains($query->sql, '`channels`')) {
                $inserted = true;
                $this->channel($owner, ['name' => '競合名', 'is_private' => '1']);
            }
        });
        $this->actingAs($owner)->post('/channels', ['name' => '競合名', 'is_private' => '0'])
            ->assertStatus(303)->assertRedirect('/channels/create')->assertSessionHasErrors(['name' => '使用できない名前です']);
        $this->assertTrue($inserted);
        $this->assertDatabaseCount('channels', 1);
        $this->assertDatabaseCount('channel_user', 1);
    }

    public function test_the_database_itself_rejects_duplicate_active_names_and_duplicate_memberships(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        try {
            DB::table('channels')->insert([
                'name' => $channel->name, 'is_private' => true, 'creator_id' => $owner->id, 'created_at' => now(),
            ]);
            $this->fail('未削除の名前が重複登録されました');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }
        try {
            $channel->members()->attach($owner);
            $this->fail('同じ所属が重複登録されました');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }
    }

    public function test_deletion_confirmation_counts_all_states_retains_every_row_and_releases_the_name(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner, ['name' => ' 削除対象 ']);
        $parent = $this->message($channel, $owner);
        $this->message($channel, $owner, ['edited_at' => now()]);
        $deleted = $this->message($channel, $owner, ['edited_at' => now(), 'deleted_at' => now()]);
        $this->message($channel, $owner, ['parent_message_id' => $parent->id]);
        $this->message($channel, $owner, ['parent_message_id' => $deleted->id, 'deleted_at' => now()]);
        $snapshot = Message::orderBy('id')->get()->toArray();
        $this->actingAs($owner)->get("/channels/{$channel->id}/edit?confirm_delete=1")->assertOk()
            ->assertSee(' 削除対象 ')->assertSee('メッセージ 3件と返信 2件')->assertSee('この操作は取り消せません');
        $this->get("/channels/{$channel->id}/edit")->assertOk()->assertDontSee('name="confirmation_name"', false);
        $this->assertNull($channel->fresh()->deleted_at);
        foreach (['', '削除対象', ' 削除対象', ' 削除対象  '] as $wrong) {
            $this->delete("/channels/{$channel->id}", ['confirmation_name' => $wrong])->assertStatus(303)
                ->assertRedirect("/channels/{$channel->id}/edit?confirm_delete=1")->assertSessionHasErrors('confirmation_name');
            $this->get("/channels/{$channel->id}/edit?confirm_delete=1")->assertSee('name="confirmation_name"', false);
            $this->assertNull($channel->fresh()->deleted_at);
        }
        $this->delete("/channels/{$channel->id}", ['confirmation_name' => $channel->name])->assertStatus(303)->assertRedirect('/channels');
        $this->assertNotNull($channel->fresh()->deleted_at);
        $this->assertSame($snapshot, Message::orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('channel_user', ['channel_id' => $channel->id, 'user_id' => $owner->id]);
        $this->get('/channels')->assertDontSee($channel->name);
        foreach (['', '/edit', '/edit?confirm_delete=1'] as $suffix) {
            $this->get("/channels/{$channel->id}{$suffix}")->assertNotFound()->assertDontSee('保持する元本文');
        }
        $this->patch("/channels/{$channel->id}", [])->assertNotFound();
        $this->delete("/channels/{$channel->id}", ['confirmation_name' => $channel->name])->assertNotFound();
        $this->post('/channels', ['name' => $channel->name, 'is_private' => '0'])->assertStatus(303)->assertSessionHasNoErrors();
        $new = Channel::active()->sole();
        $this->assertNotSame($channel->id, $new->id);
        $this->assertCount(0, $new->messages);
    }

    public function test_stale_models_cannot_update_after_deletion_and_confirmation_uses_the_current_name(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $action = app(SaveChannel::class);
        $action->update($owner, $channel, ['name' => '変更後']);
        try {
            $action->delete($owner, $channel, '開発');
            $this->fail('古い名前で削除されました');
        } catch (ValidationException $exception) {
            $this->assertSame(['チャンネル名が一致しません'], $exception->errors()['confirmation_name']);
        }
        $action->delete($owner, $channel, '変更後');
        try {
            $action->update($owner, $channel, ['name' => '古い画面から変更']);
            $this->fail('削除後に変更されました');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }
        $this->assertSame('変更後', $channel->fresh()->name);
    }

    public function test_old_input_and_errors_are_scoped_to_the_operation_and_channel(): void
    {
        $owner = User::factory()->create();
        $first = $this->channel($owner, ['name' => '最初の部屋']);
        $second = $this->channel($owner, ['name' => '次の部屋']);
        $this->actingAs($owner)->patch("/channels/{$first->id}", ['name' => '', 'description' => '入力中の説明'])->assertStatus(303);
        $this->get("/channels/{$second->id}/edit")->assertSee('次の部屋')->assertDontSee('入力中の説明')->assertDontSee('チャンネル名を入力してください');
        $this->delete("/channels/{$first->id}", ['confirmation_name' => '間違い'])->assertStatus(303);
        $this->get("/channels/{$first->id}/edit?confirm_delete=1")->assertSee('value="最初の部屋"', false)
            ->assertSee('チャンネル名が一致しません')->assertSee('value="間違い"', false);
        $this->patch("/channels/{$first->id}", ['name' => ['型違い'], 'description' => ['型違い']])->assertStatus(303);
        $this->get("/channels/{$first->id}/edit")->assertOk()->assertDontSee('型違い');
    }

    public function test_edit_keeps_a_cleared_description_when_the_name_is_invalid(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner, ['description' => '保存済みの説明']);
        $this->actingAs($owner)->patch("/channels/{$channel->id}", [
            'name' => str_repeat('あ', 51), 'description' => '',
        ])->assertStatus(303)->assertRedirect("/channels/{$channel->id}/edit")
            ->assertSessionHasErrors('name');

        $response = $this->get("/channels/{$channel->id}/edit")->assertOk();
        $response->assertViewHas('edit', fn (array $state) => array_key_exists('description', $state['values']) && $state['values']['description'] === null);
        $response->assertDontSee('保存済みの説明');
        $this->assertSame('保存済みの説明', $channel->fresh()->description);
    }

    public function test_edit_keeps_an_empty_name_instead_of_restoring_the_saved_name(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner, ['name' => '保存済みの名前']);
        $this->actingAs($owner)->patch("/channels/{$channel->id}", [
            'name' => '', 'description' => '入力した説明',
        ])->assertStatus(303)->assertSessionHasErrors(['name' => 'チャンネル名を入力してください']);

        $response = $this->get("/channels/{$channel->id}/edit")->assertOk();
        $response->assertViewHas('edit', fn (array $state) => array_key_exists('name', $state['values']) && $state['values']['name'] === null);
        $response->assertSee('チャンネル名を入力してください')->assertSee('入力した説明');
        $this->assertSame('保存済みの名前', $channel->fresh()->name);
    }

    public function test_edit_does_not_flash_array_inputs(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $this->actingAs($owner)->patch("/channels/{$channel->id}", [
            'name' => ['不正な名前'], 'description' => ['不正な説明'],
        ])->assertStatus(303)->assertSessionHasErrors(['name', 'description']);

        $this->assertArrayNotHasKey('name', session()->getOldInput());
        $this->assertArrayNotHasKey('description', session()->getOldInput());
        $this->get("/channels/{$channel->id}/edit")->assertOk()
            ->assertDontSee('不正な名前')->assertDontSee('不正な説明');
    }

    public function test_save_failure_rolls_back_the_channel_and_membership_and_hides_internal_details(): void
    {
        $owner = User::factory()->create();
        Log::spy();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into `channel_user`')) {
                throw new RuntimeException('secret SQL and body');
            }
        });
        $this->actingAs($owner)->post('/channels', ['name' => '失敗する作成', 'is_private' => '0'])->assertStatus(500)
            ->assertSee('処理に失敗したため時間をおいて再度お試しください')->assertDontSee('secret SQL and body');
        $this->assertDatabaseCount('channels', 0);
        $this->assertDatabaseCount('channel_user', 0);
        Log::shouldHaveReceived('error')->with('Application operation failed', [
            'exception_class' => RuntimeException::class, 'route' => 'channels.store',
        ])->once();
    }

    public function test_update_name_collision_after_validation_rolls_back_and_uses_the_safe_edit_url(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $inserted = false;
        DB::listen(function (QueryExecuted $query) use (&$inserted, $owner) {
            if (! $inserted && str_contains($query->sql, 'count(*)') && str_contains($query->sql, '`channels`')) {
                $inserted = true;
                $this->channel($owner, ['name' => '競合する変更先', 'is_private' => '1']);
            }
        });
        $this->actingAs($owner)->from('https://example.com/unsafe')->patch("/channels/{$channel->id}", [
            'name' => '競合する変更先', 'description' => '保存されない説明',
        ])->assertStatus(303)->assertRedirect("/channels/{$channel->id}/edit")
            ->assertSessionHasErrors(['name' => '使用できない名前です']);
        $this->assertSame('開発', $channel->fresh()->name);
        $this->assertSame('開発の相談', $channel->fresh()->description);
    }

    public function test_update_database_failure_rolls_back_and_logs_the_target_without_input(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        Log::spy();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'update `channels`')) {
                throw new RuntimeException('secret description and SQL');
            }
        });
        $this->actingAs($owner)->patch("/channels/{$channel->id}", ['name' => '保存されない変更'])
            ->assertStatus(500)->assertDontSee('secret description and SQL');
        $this->assertSame('開発', $channel->fresh()->name);
        Log::shouldHaveReceived('error')->with('Application operation failed', [
            'exception_class' => RuntimeException::class, 'route' => 'channels.update', 'channel_id' => $channel->id,
        ])->once();
    }

    public function test_response_failure_after_creation_does_not_undo_the_committed_channel_or_membership(): void
    {
        $owner = User::factory()->create();
        $this->app->instance('fail-after-save', new class
        {
            public function handle($request, \Closure $next)
            {
                $next($request);
                throw new RuntimeException('response failed after commit');
            }
        });
        Route::getRoutes()->getByName('channels.store')->middleware('fail-after-save');
        $this->actingAs($owner)->post('/channels', ['name' => '保存後の応答失敗', 'is_private' => '1'])
            ->assertStatus(500)->assertSee('画面を開き直して結果を確認してください');
        $channel = Channel::sole();
        $this->assertSame('保存後の応答失敗', $channel->name);
        $this->assertSame([$owner->id], $channel->members()->pluck('users.id')->all());
    }

    public function test_unprovided_put_method_is_rejected_without_modifying_the_channel(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $this->actingAs($owner)->put("/channels/{$channel->id}", ['name' => '変更不可'])->assertStatus(405);
        $this->assertSame('開発', $channel->fresh()->name);
    }

    public function test_fixed_channel_seeding_is_repeatable_and_does_not_overwrite_existing_memberships(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('channels', 4);
        $this->assertDatabaseCount('channel_user', 5);
        $sato = User::where('email', 'sato@example.com')->sole();
        $suzuki = User::where('email', 'suzuki@example.com')->sole();
        $takahashi = User::where('email', 'takahashi@example.com')->sole();
        foreach (['全体連絡' => $suzuki, '開発' => $sato, '雑談' => $takahashi, '採用プロジェクト' => $sato] as $name => $creator) {
            $channel = Channel::where('name', $name)->sole();
            $this->assertSame($creator->id, $channel->creator_id);
            $this->assertSame($name === '採用プロジェクト', $channel->is_private);
            $this->assertContains($creator->id, $channel->members()->pluck('users.id')->all());
        }
        $private = Channel::where('name', '採用プロジェクト')->sole();
        $this->assertSame([$sato->id, $suzuki->id], $private->members()->orderBy('users.id')->pluck('users.id')->all());
        $private->members()->detach($suzuki);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('channels', 4);
        $this->assertCount(1, $private->fresh()->members);
    }

    public function test_jst_accessors_do_not_change_the_utc_dates_or_saved_values(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $channel->forceFill(['created_at' => '2026-10-30 18:20:00', 'deleted_at' => '2026-10-30 19:20:00'])->save();
        $channel->refresh();
        $this->assertSame('2026/10/31 03:20', $channel->created_at_jst->format('Y/m/d H:i'));
        $this->assertSame('2026/10/31 04:20', $channel->deleted_at_jst->format('Y/m/d H:i'));
        $this->assertSame('UTC', $channel->created_at->timezoneName);
        $this->assertSame('2026-10-30 18:20:00', $channel->created_at->format('Y-m-d H:i:s'));
        $this->assertFalse($channel->isDirty());
        $message = $this->message($channel, $owner, ['edited_at' => '2026-10-30 23:00:00']);
        $this->assertSame('2026/10/31 08:00', $message->edited_at_jst->format('Y/m/d H:i'));
        $this->assertNull($message->deleted_at_jst);
    }

    public function test_views_escape_user_content_have_scoped_navigation_and_do_not_load_javascript(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner, ['name' => '<b>部屋</b>', 'description' => '<script>秘密</script>', 'is_private' => '1']);
        $this->actingAs($owner)->get('/channels')->assertOk()->assertSee('&lt;b&gt;部屋&lt;/b&gt;', false)
            ->assertSee('&lt;script&gt;秘密&lt;/script&gt;', false)->assertSee('disabled>メンバー', false)->assertDontSee('<script', false);
        $this->get("/channels/{$channel->id}")->assertOk()->assertSee('aria-current="page"', false);
        $this->get('/channels/create')->assertOk()->assertSee('50文字まで')->assertSee('200文字まで')->assertSee('name="_token"', false);
        $this->get("/channels/{$channel->id}/edit")->assertOk()->assertSee('公開範囲はあとから変更できません')->assertDontSee('name="is_private"', false);
        $this->get("/channels/{$channel->id}/edit?confirm_delete[]=1")->assertStatus(422)->assertSee('表示条件が正しくありません');
    }

    public function test_empty_channels_and_csrf_errors_are_handled_without_writes(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/channels')->assertOk()->assertSee('チャンネルがありません');
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/channels', ['name' => '作られない', 'is_private' => '0'])->assertStatus(302)->assertRedirect('/login');
        $this->assertDatabaseCount('channels', 0);
        $this->assertAuthenticatedAs($owner);
    }
}
