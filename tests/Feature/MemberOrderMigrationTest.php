<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberOrderMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_existing_memberships_are_preserved_and_numbered_with_keys_intact(): void
    {
        $migration = require database_path('migrations/2026_11_05_000000_add_joined_order_to_channel_user_table.php');
        $migration->down();
        $owner = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $channel = app(SaveChannel::class)->create($owner, ['name' => 'First channel', 'is_private' => '1']);
        $other = app(SaveChannel::class)->create($owner, ['name' => 'Second channel', 'is_private' => '1']);
        $other->members()->attach($first);
        $channel->members()->attach($second);
        $channel->members()->attach($first);
        $before = DB::table('channel_user')->orderBy('channel_id')->orderBy('user_id')->get()->map(fn ($row) => (array) $row)->all();

        $migration->up();

        $rows = DB::table('channel_user')->orderBy('joined_order')->get();
        $this->assertSame($before, $rows->map(fn ($row) => ['channel_id' => $row->channel_id, 'user_id' => $row->user_id])->all());
        $this->assertSame(range(1, count($before)), $rows->pluck('joined_order')->all());
        $new = User::factory()->create();
        $channel->members()->attach($new);
        $this->assertGreaterThan(count($before), DB::table('channel_user')->where('user_id', $new->id)->value('joined_order'));

        $this->assertConstraintRejects(fn () => $channel->members()->attach($first));
        $this->assertConstraintRejects(fn () => DB::table('channel_user')->insert(['channel_id' => $other->id, 'user_id' => $new->id, 'joined_order' => 1]));
        $this->assertConstraintRejects(fn () => DB::table('channel_user')->insert(['channel_id' => $channel->id, 'user_id' => 999999]));
        $this->assertConstraintRejects(fn () => DB::table('channel_user')->insert(['channel_id' => 999999, 'user_id' => $new->id]));
        $this->assertConstraintRejects(fn () => DB::table('users')->where('id', $new->id)->delete());
        $this->assertConstraintRejects(fn () => DB::table('channels')->where('id', $other->id)->delete());
        $this->assertDatabaseCount('channel_user', count($before) + 1);
    }

    private function assertConstraintRejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected membership constraint rejection');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
    }
}
