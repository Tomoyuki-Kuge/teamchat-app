<?php

namespace Tests\Unit;

use App\Support\AuthInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthInputTest extends TestCase
{
    #[DataProvider('emails')]
    public function test_email_normalization_preserves_the_local_part(string $input, string $expected): void
    {
        $this->assertSame($expected, AuthInput::email($input));
    }

    public static function emails(): array
    {
        return [
            [" \r\nUser@EXAMPLE.COM\t ", 'User@example.com'],
            ['　Ｕser@EXAMPLE.COM　', 'Ｕser@example.com'],
            ["e\u{0301}@EXAMPLE.COM", "e\u{0301}@example.com"],
            ['User＠EXAMPLE.COM', 'User＠EXAMPLE.COM'],
            ['invalid', 'invalid'],
            ["　\n ", ''],
        ];
    }
}
