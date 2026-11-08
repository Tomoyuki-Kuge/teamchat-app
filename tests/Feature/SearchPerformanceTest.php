<?php

namespace Tests\Feature;

use App\Actions\Channels\SaveChannel;
use App\Models\User;
use App\Support\Search\MessageSearch;
use App\Support\Search\SearchText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_search_keeps_only_one_page_and_observes_time_and_peak_memory(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        DB::disableQueryLog();
        $user = User::factory()->create();
        $channel = app(SaveChannel::class)->create($user, ['name' => '性能確認', 'is_private' => '0']);
        $body = 'ＡＢＣ ｶﾞ① '.str_repeat('検証本文', 245);
        $measurements = [];
        foreach ([1000, 10000] as $count) {
            $existing = DB::table('messages')->count();
            for ($i = $existing; $i < $count; $i += 100) {
                $rows = array_fill(0, min(100, $count - $i), ['channel_id' => $channel->id, 'user_id' => $user->id,
                    'body' => $body, 'created_at' => '2026-10-30 00:00:00', 'version' => 1]);
                DB::table('messages')->insert($rows);
            }
            unset($rows);
            gc_collect_cycles();
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $baseline = memory_get_usage(false);
            $start = hrtime(true);
            $result = app(MessageSearch::class)->find($user, SearchText::words('abc ガ 1'), 'newest', 1, null);
            foreach ($result['results'] as $message) {
                SearchText::highlight($message->body, SearchText::words('abc ガ 1'));
            }
            $seconds = (hrtime(true) - $start) / 1e9;
            $peak = memory_get_peak_usage(false);
            $measurements[] = sprintf('%d rows: %.3fs, baseline %.2f MiB, peak %.2f MiB, delta %.2f MiB', $count, $seconds, $baseline / 1048576, $peak / 1048576, ($peak - $baseline) / 1048576);
            $this->assertSame($count, $result['total']);
            $this->assertCount(10, $result['results']);
            unset($result);
        }
        fwrite(STDOUT, "\nSearch performance (query log off; scan + 10 highlights):\n".implode("\n", $measurements)."\n");
    }
}
