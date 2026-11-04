<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Actions\Channels\SaveMember;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private function channel(User $creator, bool $private = true): Channel
    {
        return app(SaveChannel::class)->create($creator, ['name' => fake()->unique()->word(), 'is_private' => $private ? '1' : '0']);
    }

    public function test_guest_management_requests_require_login(): void
    {
        $channel = $this->channel(User::factory()->create());
        $this->get("/channels/{$channel->id}/members")->assertRedirect('/login');
        $this->post("/channels/{$channel->id}/members")->assertRedirect('/login');
        $this->delete("/channels/{$channel->id}/members/1")->assertRedirect('/login');
        $this->assertDatabaseCount('channel_user', 1);
    }

    public function test_csrf_rejection_does_not_change_memberships(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $channel = $this->channel($owner);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($owner)->post(route('members.store', $channel), ['user_id' => $target->id])->assertRedirect('/login');
        $this->assertDatabaseCount('channel_user', 1);
    }

    public function test_visibility_and_management_authorization_precede_input_validation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $private = $this->channel($owner);
        $private->members()->attach($member);
        $public = $this->channel($owner, false);
        foreach ([[$outsider, $private, 404], [$member, $private, 403], [$owner, $public, 403]] as [$actor, $channel, $status]) {
            $url = "/channels/{$channel->id}/members";
            $this->actingAs($actor)->get($url.'?q[]=secret')->assertStatus($status);
            $this->post($url, ['user_id' => [], 'q' => []])->assertStatus($status);
            $response = $this->delete($url.'/'.$owner->id, ['q' => []])->assertStatus($status);
            if ($status === 403) {
                $response->assertSee('この操作はできません')->assertDontSee('作成者は外せません');
            } else {
                $response->assertDontSee('作成者は外せません');
            }
        }
        $private->deleted_at = now();
        $private->save();
        $this->actingAs($owner)->get("/channels/{$private->id}/members")->assertNotFound();
        $this->post("/channels/{$private->id}/members", [])->assertNotFound();
    }

    public function test_member_list_owner_protection_and_management_links(): void
    {
        $owner = User::factory()->create(['name' => '管理者']);
        $channel = $this->channel($owner);
        $member = User::factory()->create(['name' => '社員']);
        $channel->members()->attach($member);
        $url = route('members.index', $channel);
        $this->actingAs($owner)->get('/channels')->assertSee($url, false);
        $this->get(route('channels.show', $channel))->assertSee($url, false);
        $this->get($url)->assertOk()->assertSee('メンバー　2人')->assertSee($member->email)->assertSee('作成者は外せません')->assertSee('disabled', false);
        $this->delete("$url/{$owner->id}")->assertForbidden()->assertSee('作成者は外せません');
        $this->delete("$url/{$owner->id}", ['q' => []])->assertForbidden()->assertSee('作成者は外せません')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('channel_user', ['channel_id' => $channel->id, 'user_id' => $owner->id]);
        $this->actingAs($member)->get('/channels')->assertDontSee($url, false);
    }

    public function test_member_list_places_creator_first_then_join_order_and_rejoining_last(): void
    {
        $owner = User::factory()->create(['name' => 'Z owner']);
        $earlierId = User::factory()->create(['name' => 'A second']);
        $laterId = User::factory()->create(['name' => 'Z first']);
        $channel = $this->channel($owner);
        $channel->members()->detach($owner);
        $channel->members()->attach($laterId);
        $channel->members()->attach($earlierId);
        $channel->members()->attach($owner);
        $url = route('members.index', $channel);
        $this->actingAs($owner);

        $this->assertSame([$owner->id, $laterId->id, $earlierId->id], $this->get($url)->assertOk()['members']->modelKeys());
        $order = DB::table('channel_user')->where('channel_id', $channel->id)->where('user_id', $laterId->id)->value('joined_order');
        $this->post($url, ['user_id' => $laterId->id])->assertStatus(303);
        $this->assertSame($order, DB::table('channel_user')->where('channel_id', $channel->id)->where('user_id', $laterId->id)->value('joined_order'));
        $this->assertSame([$owner->id, $laterId->id, $earlierId->id], $this->get($url)->assertOk()['members']->modelKeys());

        $this->delete("$url/{$laterId->id}")->assertStatus(303);
        $this->post($url, ['user_id' => $laterId->id])->assertStatus(303);
        $this->assertSame([$owner->id, $earlierId->id, $laterId->id], $this->get($url)->assertOk()['members']->modelKeys());
    }

    public function test_locked_action_refuses_creator_removal_without_changing_memberships(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);

        try {
            app(SaveMember::class)->remove($owner, $channel, $owner->id);
            $this->fail('Expected creator removal rejection');
        } catch (AuthorizationException $exception) {
            $this->assertSame('作成者は外せません', $exception->getMessage());
        }

        $this->assertDatabaseHas('channel_user', ['channel_id' => $channel->id, 'user_id' => $owner->id]);
        $this->assertDatabaseCount('channel_user', 1);
    }

    public function test_candidates_use_nfkc_ascii_case_literal_whole_query_and_database_order(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $first = User::factory()->create(['name' => 'ＡＢＣ ｶﾅ① %_ 同名', 'email' => 'first@example.com']);
        $second = User::factory()->create(['name' => $first->name, 'email' => 'second@example.com']);
        User::factory()->create(['name' => 'ABC separated words', 'email' => 'other@example.com']);
        $url = route('members.index', $channel);
        foreach (['abc カナ1', '%_', 'FIRST@EXAMPLE'] as $q) {
            $response = $this->actingAs($owner)->get($url.'?'.http_build_query(['q' => $q]))->assertOk();
            $this->assertTrue($response['candidates']->contains('id', $first->id));
        }
        $response = $this->get($url.'?'.http_build_query(['q' => 'abc カナ1']));
        $this->assertSame([$first->id, $second->id], $response['candidates']->pluck('id')->all());
        $this->get($url.'?q=ABC+words')->assertSee('追加できるメンバーが見つかりません');
        $channel->members()->attach($first);
        $response = $this->get($url.'?q=first%40example');
        $this->assertCount(0, $response['candidates']);
        $this->assertSame('ＡＢＣ ｶﾅ① %_ 同名', $first->fresh()->name);
    }

    public function test_empty_searches_invalid_types_and_unbounded_search_input(): void
    {
        $owner = User::factory()->create();
        $url = route('members.index', $this->channel($owner));
        foreach (['', "　 \t"] as $q) {
            $response = $this->actingAs($owner)->get($url.'?'.http_build_query(['q' => $q]))->assertOk();
            $this->assertFalse($response['searched']);
            $this->assertCount(0, $response['candidates']);
        }
        $this->get($url.'?q[]=x')->assertStatus(422)->assertSee('検索キーワードを正しい形式で入力してください');
        $this->get($url.'?q='.str_repeat('a', 1100))->assertOk()->assertSee('追加できるメンバーが見つかりません');
    }

    public function test_addition_is_idempotent_preserves_query_and_immediately_allows_reading_and_posting(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $channel = $this->channel($owner);
        $url = route('members.index', $channel);
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($owner)->post($url, ['user_id' => $member->id, 'q' => '社員', 'return_url' => 'https://example.com'])->assertStatus(303)->assertRedirect($url.'?'.http_build_query(['q' => '社員']));
        }
        $this->assertDatabaseCount('channel_user', 2);
        $this->actingAs($member)->get(route('channels.show', $channel))->assertOk();
        $this->post(route('messages.store', $channel), ['body' => '参加できた'])->assertStatus(303);
        $this->assertDatabaseHas('messages', ['user_id' => $member->id, 'body' => '参加できた']);
    }

    public function test_invalid_addition_returns_to_fixed_form_with_channel_scoped_feedback(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $other = $this->channel($owner);
        $url = route('members.index', $channel);
        foreach ([null, [], 'no', 999999] as $id) {
            $this->actingAs($owner)->from('https://example.com')->post($url, ['user_id' => $id, 'q' => '維持'])->assertStatus(303)->assertRedirect($url.'?'.http_build_query(['q' => '維持']))->assertSessionHasErrors('user_id');
            $this->get($url.'?'.http_build_query(['q' => '維持']))->assertOk()->assertSee('追加するメンバーを選択してください');
        }
        $this->post($url, ['user_id' => [], 'q' => []])->assertStatus(303)->assertSessionHasErrors(['q', 'user_id']);
        $this->get(route('members.index', $other))->assertOk()->assertDontSee('追加するメンバーを選択してください');
        $this->assertDatabaseCount('channel_user', 2);
    }

    public function test_removal_is_idempotent_revokes_all_existing_message_operations_and_preserves_history(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $channel = $this->channel($owner);
        $channel->members()->attach($member);
        $post = new Message;
        $post->forceFill(['channel_id' => $channel->id, 'user_id' => $member->id, 'body' => '保持本文', 'version' => 1])->save();
        $reply = new Message;
        $reply->forceFill(['channel_id' => $channel->id, 'user_id' => $member->id, 'body' => '保持返信', 'parent_message_id' => $post->id, 'version' => 1])->save();
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($owner)->delete(route('members.destroy', ['channel' => $channel, 'user' => $member->id]))->assertStatus(303);
        }
        $url = route('channels.show', $channel);
        $this->actingAs($member)->get($url)->assertNotFound()->assertDontSee('保持本文');
        $this->post("$url/messages", ['body' => '拒否'])->assertNotFound();
        $this->patch("$url/messages/{$post->id}", ['body' => '', 'expected_version' => 1])->assertNotFound();
        $this->get("$url/messages/{$post->id}/delete-confirmation")->assertNotFound();
        $this->delete("$url/messages/{$post->id}")->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertDatabaseHas('messages', ['id' => $post->id, 'body' => '保持本文', 'deleted_at' => null]);
        $this->assertDatabaseHas('messages', ['id' => $reply->id, 'body' => '保持返信', 'parent_message_id' => $post->id]);
    }

    public function test_removal_invalid_query_and_unregistered_user_keep_operation_feedback(): void
    {
        $owner = User::factory()->create();
        $channel = $this->channel($owner);
        $member = User::factory()->create();
        $channel->members()->attach($member);
        $url = route('members.index', $channel);
        $this->actingAs($owner)->delete("$url/{$member->id}", ['q' => []])->assertStatus(303)->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('検索キーワードを正しい形式で入力してください');
        $this->delete("$url/999999", ['q' => '保持'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('対象のメンバーを選択してください');
        $this->assertDatabaseHas('channel_user', ['channel_id' => $channel->id, 'user_id' => $member->id]);
    }

    public function test_locked_action_rechecks_current_visibility_and_creator(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $channel = $this->channel($owner);
        $channel->members()->detach($owner);
        try {
            app(SaveMember::class)->add($owner, $channel, $target->id);
            $this->fail('Expected current visibility rejection');
        } catch (AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }
        $this->assertDatabaseMissing('channel_user', ['channel_id' => $channel->id, 'user_id' => $target->id]);
        $channel->members()->attach($owner);
        DB::table('channels')->where('id', $channel->id)->update(['creator_id' => $target->id]);
        try {
            app(SaveMember::class)->remove($owner, $channel, $target->id);
            $this->fail('Expected current creator rejection');
        } catch (AuthorizationException $exception) {
            $this->assertSame('この操作はできません', $exception->getMessage());
        }
    }

    public function test_failure_after_membership_write_rolls_back_and_logs_without_input(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $channel = $this->channel($owner);
        Log::spy();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'insert into `channel_user`')) {
                throw new RuntimeException('secret SQL input');
            }
        });
        $this->actingAs($owner)->post(route('members.store', $channel), ['user_id' => $target->id, 'q' => 'secret search'])->assertStatus(500)->assertDontSee('secret');
        $this->assertDatabaseMissing('channel_user', ['channel_id' => $channel->id, 'user_id' => $target->id]);
        Log::shouldHaveReceived('error')->once()->with('Application operation failed', ['exception_class' => RuntimeException::class, 'route' => 'members.store', 'channel_id' => $channel->id]);
    }
}
