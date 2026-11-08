<?php

namespace Tests\Unit;

use App\Support\Search\SearchText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SearchTextTest extends TestCase
{
    public function test_words_deduplicate_after_normalizing_and_preserve_numeric_strings(): void
    {
        $this->assertSame(['abc', 'ガ', '0', '1', '01'], SearchText::words('ＡＢＣ abc ABC ｶﾞ ガ ０ 0 ① 1 01'));
    }

    #[DataProvider('memoryCases')]
    public function test_many_overlapping_matches_complete_within_a_finite_memory_limit(string $case, int $wordCount): void
    {
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', __DIR__.'/../Support/search-highlight-memory.php', $case]);
        $process->setTimeout(30);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('<mark>'.str_repeat('a', 1000).'</mark>', $result['html']);
        $this->assertSame($wordCount, $result['words']);
        $this->assertLessThan(128 * 1024 * 1024, $result['peak']);
    }

    public static function memoryCases(): array
    {
        return ['repeated word' => ['repeated', 1], 'distinct overlapping words' => ['distinct', 1000]];
    }

    #[DataProvider('highlights')]
    public function test_normalized_matches_highlight_only_corresponding_original_ranges(string $body, string $query, string $expected): void
    {
        $this->assertSame($expected, (string) SearchText::highlight($body, SearchText::words($query)));
    }

    public static function highlights(): array
    {
        return [
            ['', 'a', ''],
            ['abc', 'z', 'abc'],
            ['abc', 'c a', '<mark>a</mark>b<mark>c</mark>'],
            ['abc', 'b a', '<mark>ab</mark>c'],
            ['前ＡＢＣ後', 'abc', '前<mark>ＡＢＣ</mark>後'],
            ['前ｶﾞ後', 'ガ', '前<mark>ｶﾞ</mark>後'],
            ['前㍿後', '会社', '前<mark>㍿</mark>後'],
            ['前①後', '1', '前<mark>①</mark>後'],
            ["前e\u{0301}後", 'é', "前<mark>e\u{0301}</mark>後"],
            ['前ㄱㅏ後', '가', '前<mark>ㄱㅏ</mark>後'],
            ['前ㄱㅏㄴ後', '가', '前<mark>ㄱㅏ</mark>ㄴ後'],
            ['前ㄱㅏㄴ後', 'ᄂ', '前ㄱㅏ<mark>ㄴ</mark>後'],
            ['前각後', '각', '前<mark>각</mark>後'],
            ['banana', 'ana nan', 'b<mark>anana</mark>'],
            ['<script>Ａ</script> & ㍿', 'a 会社', '&lt;script&gt;<mark>Ａ</mark>&lt;/script&gt; &amp; <mark>㍿</mark>'],
            ['前ㄱㅏ と ｶﾞ後', '가 ガ', '前<mark>ㄱㅏ</mark> と <mark>ｶﾞ</mark>後'],
        ];
    }
}
