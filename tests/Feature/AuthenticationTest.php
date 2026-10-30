<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Database\Seeders\AuthUserSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => '登録 社員',
            'email' => 'member@example.com',
            'password' => ' password日本語 ',
            'password_confirmation' => ' password日本語 ',
        ], $overrides);
    }

    private function enableCsrf(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_authentication_uses_fortify_and_only_enables_registration(): void
    {
        $this->assertSame([Features::registration()], config('fortify.features'));
        $this->assertFalse(config('fortify.lowercase_usernames'));
        $this->assertSame(AuthenticatedSessionController::class.'@store', Route::getRoutes()->getByName('login.store')->getActionName());
        $this->assertSame(RegisteredUserController::class.'@store', Route::getRoutes()->getByName('register.store')->getActionName());
        $this->assertSame(AuthenticatedSessionController::class.'@destroy', Route::getRoutes()->getByName('logout')->getActionName());

        foreach (['/forgot-password', '/reset-password/token', '/email/verify', '/two-factor-challenge', '/user/confirm-password', '/user/confirmed-password-status'] as $path) {
            $this->get($path)->assertNotFound();
        }
        foreach (['/forgot-password', '/reset-password', '/email/verification-notification', '/two-factor-challenge', '/user/confirm-password', '/user/two-factor-authentication'] as $path) {
            $this->post($path)->assertNotFound();
        }
        foreach (['/user/profile-information', '/user/password'] as $path) {
            $this->put($path)->assertNotFound();
        }
    }

    public function test_registration_normalizes_email_hashes_the_whole_password_and_logs_in(): void
    {
        $this->withSession(['marker' => 'before']);
        $oldSessionId = session()->getId();
        $input = $this->registration(['email' => "　Member@EXAMPLE.COM\r\n", 'remember' => true]);

        $this->post('/register', $input)->assertStatus(303)->assertRedirect('/channels');

        $user = User::sole();
        $this->assertSame('Member@example.com', $user->email);
        $this->assertSame('argon2id', password_get_info($user->password_hash)['algoName']);
        $this->assertTrue(Hash::check($input['password'], $user->password_hash));
        $this->assertFalse(Hash::check(trim($input['password']), $user->password_hash));
        $this->assertNull($user->remember_token);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertAuthenticatedAs($user);
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
        $this->get('/channels')->assertOk()->assertSee('登録 社員')->assertSee('ログアウト')
            ->assertSee('チームチャットアプリ')->assertSee('チャンネル一覧')
            ->assertSee('<nav', false)->assertSee('チャンネル一覧を見る')
            ->assertDontSee('メッセージを検索')->assertSee('公開チャンネルは');
    }

    #[DataProvider('invalidRegistrationInputs')]
    public function test_invalid_registration_never_creates_a_user_or_flashes_passwords(array $overrides, string $field, string $message): void
    {
        $this->post('/register', $this->registration($overrides))
            ->assertStatus(303)->assertRedirect('/register')
            ->assertSessionHasErrors([$field => $message])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    #[DataProvider('arrayFormInputs')]
    public function test_array_inputs_are_not_redisplayed_and_the_form_still_opens(string $path, string $field): void
    {
        $input = $this->registration([$field => ['invalid-value']]);

        $this->post($path, $input)->assertStatus(303)->assertRedirect($path)
            ->assertSessionHasErrors($field)
            ->assertSessionMissing('_old_input.'.$field);

        $this->get($path)->assertOk()
            ->assertSee($field === 'name' ? '表示名を入力してください' : 'メールアドレスを正しい形式で入力してください')
            ->assertDontSee('invalid-value');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function arrayFormInputs(): array
    {
        return [
            ['/register', 'name'],
            ['/register', 'email'],
            ['/login', 'email'],
        ];
    }

    public static function invalidRegistrationInputs(): array
    {
        return [
            [['name' => '　 '], 'name', '表示名を入力してください'],
            [['name' => str_repeat('名', 31)], 'name', '表示名は30文字以内で入力してください'],
            [['email' => ''], 'email', 'メールアドレスを入力してください'],
            [['email' => "member@exa\r\nmple.com"], 'email', 'メールアドレスを正しい形式で入力してください'],
            [['email' => 'invalid'], 'email', 'メールアドレスを正しい形式で入力してください'],
            [['email' => str_repeat('a', 256)], 'email', 'メールアドレスは255文字以内で入力してください'],
            [['email' => ['member@example.com']], 'email', 'メールアドレスを正しい形式で入力してください'],
            [['password' => '　　'], 'password', 'パスワードを入力してください'],
            [['password' => '1234567'], 'password', 'パスワードは8文字以上で入力してください'],
            [['password' => str_repeat('あ', 73)], 'password', 'パスワードは72文字以内で入力してください'],
            [['password_confirmation' => ''], 'password_confirmation', 'パスワード（確認用）を入力してください'],
            [['password_confirmation' => 'different'], 'password_confirmation', 'パスワード（確認用）が一致しません'],
        ];
    }

    #[DataProvider('validBoundaries')]
    public function test_registration_accepts_boundary_lengths(string $name, string $email, string $password): void
    {
        $this->post('/register', $this->registration([
            'name' => $name, 'email' => $email,
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertStatus(303)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => $name, 'email' => $email]);
        $this->assertTrue(Hash::check($password, User::sole()->password_hash));
    }

    public static function validBoundaries(): array
    {
        $longEmail = str_repeat('a', 64).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 60).'.'.str_repeat('e', 7);

        return [
            [str_repeat('名', 30), 'boundary@example.com', '12345678'],
            ['社員', $longEmail, str_repeat('あ', 72)],
        ];
    }

    public function test_duplicate_normalized_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'Member@example.com']);
        $this->post('/register', $this->registration(['email' => " Member@EXAMPLE.COM\n"]))
            ->assertStatus(303)->assertSessionHasErrors(['email' => 'このメールアドレスは登録されています']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    #[DataProvider('distinctEmails')]
    public function test_database_and_registration_preserve_local_part_differences(string $first, string $second): void
    {
        User::factory()->create(['email' => $first]);
        $this->post('/register', $this->registration(['email' => $second]))
            ->assertStatus(303)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 2);
        $this->assertSame($second, auth()->user()->email);
    }

    public static function distinctEmails(): array
    {
        return [
            ['Member@example.com', 'member@example.com'],
            ['A@example.com', 'Ａ@example.com'],
            ['é@example.com', "e\u{0301}@example.com"],
        ];
    }

    public function test_simultaneous_email_registration_returns_the_same_validation_error(): void
    {
        User::creating(function (User $user) {
            DB::table('users')->insert([
                'name' => '先に登録した社員', 'email' => $user->email,
                'password_hash' => Hash::make('password'),
            ]);
        });
        $this->post('/register', $this->registration())
            ->assertStatus(303)->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'このメールアドレスは登録されています'])
            ->assertSessionMissing('_old_input.password');
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_login_normalizes_email_always_redirects_to_channels_and_ignores_remember_me(): void
    {
        $user = User::factory()->create(['email' => 'Member@example.com', 'password_hash' => ' password日本語 ']);
        $rememberToken = $user->remember_token;
        $this->withSession(['url.intended' => '/unrelated']);
        $oldSessionId = session()->getId();
        $this->post('/login', ['email' => " Member@EXAMPLE.COM\r\n", 'password' => ' password日本語 ', 'remember' => true])
            ->assertStatus(303)->assertRedirect('/channels');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertSame($rememberToken, $user->fresh()->remember_token);
        $this->get('/channels')->assertOk()->assertSee($user->name);
        $this->get('/login')->assertRedirect('/channels');
        $this->get('/register')->assertRedirect('/channels');
    }

    #[DataProvider('incorrectCredentials')]
    public function test_incorrect_login_stays_unauthenticated_with_a_common_error(string $email, string $password): void
    {
        User::factory()->create(['email' => 'Member@example.com', 'password_hash' => ' password日本語 ']);
        $this->post('/login', ['email' => $email, 'password' => $password])
            ->assertStatus(303)->assertRedirect('/login')
            ->assertSessionHasErrors(['password' => 'メールアドレスまたはパスワードが正しくありません'])
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
        $this->get('/login')->assertSee('メールアドレスまたはパスワードが正しくありません');
    }

    public static function incorrectCredentials(): array
    {
        return [
            ['absent@example.com', 'password'],
            ['member@example.com', ' password日本語 '],
            ['Member@example.com', 'password日本語'],
            ['Member@example.com', '1234567'],
        ];
    }

    public function test_login_does_not_apply_registration_minimum_password_length(): void
    {
        $user = User::factory()->create(['email' => 'short@example.com', 'password_hash' => 'short']);
        $this->post('/login', ['email' => $user->email, 'password' => 'short'])
            ->assertStatus(303)->assertRedirect('/channels');
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('invalidLoginInputs')]
    public function test_login_validates_input_before_authentication(array $input, string $field): void
    {
        $this->post('/login', $input)->assertStatus(303)->assertSessionHasErrors($field);
        $this->assertGuest();
    }

    public static function invalidLoginInputs(): array
    {
        return [
            [['email' => str_repeat('a', 256), 'password' => 'password'], 'email'],
            [['email' => 'wrong', 'password' => 'password'], 'email'],
            [['email' => "member@exa\r\nmple.com", 'password' => 'password'], 'email'],
            [['email' => 'valid@example.com', 'password' => '　 '], 'password'],
            [['email' => [], 'password' => 'password'], 'email'],
        ];
    }

    public function test_logout_invalidates_session_and_protects_channels(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['marker' => 'old', '_token' => 'old-token']);
        $oldSessionId = session()->getId();
        $this->post('/logout')->assertStatus(303)->assertRedirect('/login')->assertSessionMissing('marker');
        $this->assertGuest();
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->get('/channels')->assertRedirect('/login');
    }

    public function test_guests_cannot_access_channels_or_logout_and_get_does_not_logout(): void
    {
        $this->get('/channels')->assertRedirect('/login');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/logout')->assertStatus(405);
    }

    public function test_unauthenticated_json_requests_return_401_without_redirecting(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Location')
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->get('/channels')->assertStatus(302)->assertRedirect('/login');
    }

    public function test_csrf_is_checked_before_authentication_and_login_throttling(): void
    {
        $this->enableCsrf();
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'member@example.com', 'password' => 'password'])->assertStatus(302)->assertRedirect('/login');
        }
        $this->post('/register', $this->registration())->assertStatus(302)->assertRedirect('/login');
        $this->post('/logout')->assertStatus(302)->assertRedirect('/login');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
        $this->withSession(['_token' => 'valid-token'])
            ->post('/register', $this->registration(['_token' => 'valid-token']))->assertStatus(303);
    }

    #[DataProvider('staleCsrfFormPaths')]
    public function test_stale_csrf_tokens_redirect_to_login_without_executing_the_form(string $path): void
    {
        $this->enableCsrf();
        // セッション側のトークンを更新し、古いフォームからの送信を再現します。
        $this->withSession(['_token' => 'current-token'])
            ->post($path, $this->registration(['_token' => 'stale-token']))
            ->assertStatus(302)->assertRedirect('/login')
            ->assertSessionMissing('_old_input');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get('/login')->assertOk()->assertSee('ログイン');
    }

    public static function staleCsrfFormPaths(): array
    {
        return [['/login'], ['/register'], ['/logout']];
    }

    public function test_stale_csrf_token_does_not_log_out_an_authenticated_user(): void
    {
        $this->enableCsrf();
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['_token' => 'current-token'])
            ->post('/logout', ['_token' => 'stale-token'])
            ->assertStatus(302)->assertRedirect('/login');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout', ['_token' => 'current-token'])->assertStatus(303)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_stale_csrf_token_in_a_json_request_returns_419_without_redirecting(): void
    {
        $this->enableCsrf();
        $this->withSession(['_token' => 'current-token'])
            ->postJson('/register', $this->registration(['_token' => 'stale-token']))
            ->assertStatus(419)->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Location')->assertJsonPath('message', 'CSRF token mismatch.');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_is_limited_to_five_attempts_per_normalized_email_and_ip(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        for ($i = 0; $i < 5; $i++) {
            $email = $i % 2 === 0 ? 'member@EXAMPLE.COM' : "　member@example.com\n";
            $this->post('/login', ['email' => $email, 'password' => 'incorrect'])->assertStatus(303);
        }
        $this->post('/login', ['email' => 'member@example.com', 'password' => 'password'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertSee('ログインの試行回数が多すぎます');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'member@example.com', 'password' => 'password'])
            ->assertStatus(303)->assertRedirect('/channels');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_limits_are_independent_for_other_ips_and_local_part_case(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'Member@example.com', 'password' => 'incorrect'])->assertStatus(303);
        }
        $this->post('/login', ['email' => 'member@example.com', 'password' => 'incorrect'])->assertStatus(303);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post('/login', ['email' => 'Member@example.com', 'password' => 'incorrect'])->assertStatus(303);
    }

    public function test_fixed_accounts_are_seeded_once_with_argon2id_passwords(): void
    {
        $this->seed(AuthUserSeeder::class);
        $hashes = User::pluck('password_hash', 'email')->all();
        $this->seed(AuthUserSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertSame($hashes, User::pluck('password_hash', 'email')->all());
        foreach (['sato@example.com' => '佐藤 太郎', 'suzuki@example.com' => '鈴木 花子', 'takahashi@example.com' => '高橋 健'] as $email => $name) {
            $user = User::where('email', $email)->sole();
            $this->assertSame($name, $user->name);
            $this->assertSame('argon2id', password_get_info($user->password_hash)['algoName']);
            $this->assertTrue(Hash::check('password', $user->password_hash));
        }
    }

    public function test_db_errors_and_logs_omit_bindings_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        User::creating(function () {
            throw new QueryException('mysql', 'insert secret ?', ['secret-password'], new RuntimeException('private SQL details'));
        });
        $this->post('/register', $this->registration())
            ->assertStatus(500)->assertSee('処理に失敗したため時間をおいて再度お試しください')
            ->assertDontSee('secret-password')->assertDontSee('private SQL details');
        Log::shouldHaveReceived('error')->once()->with('Application operation failed', [
            'exception_class' => QueryException::class, 'route' => 'register.store',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_failure_after_save_keeps_the_account(): void
    {
        Event::listen(Registered::class, function () {
            throw new RuntimeException('response failure');
        });
        $this->post('/register', $this->registration())->assertStatus(500)->assertSee('操作が完了している場合があります');
        $this->assertDatabaseHas('users', ['email' => 'member@example.com']);
    }

    public function test_hash_shaped_password_is_hashed_as_literal_input(): void
    {
        // 72文字以内のハッシュ文字列も、入力されたパスワードとして扱うことを確認します。
        $password = Hash::driver('bcrypt')->make('another-password');
        $this->post('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))
            ->assertStatus(303)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, User::sole()->password_hash));
        $this->assertFalse(Hash::check('another-password', User::sole()->password_hash));
    }

    public function test_forms_use_csrf_and_escape_redisplayed_name(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="_token"', false)->assertDontSee('<script', false);
        $this->get('/register')->assertOk()->assertSee('8〜72文字で入力してください')->assertSee('30文字まで');
        $this->post('/register', $this->registration(['name' => '<b>社員</b>', 'password_confirmation' => 'wrong']))
            ->assertSessionHasInput('name', '<b>社員</b>');
        $this->get('/register')->assertSee('&lt;b&gt;社員&lt;/b&gt;', false)->assertDontSee('value=" password日本語 "', false);
    }
}
