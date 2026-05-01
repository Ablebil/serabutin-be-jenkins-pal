<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\EmailVerificationTokenService;
use App\Services\Auth\RefreshTokenService;
use Database\Factories\UserFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    private string $testSchema;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_pgsql extension is required for auth feature tests.');
        }

        $this->configurePostgresConnection();

        if (!$this->canConnectToPostgres()) {
            $this->markTestSkipped('PostgreSQL test connection is not available. Configure TEST_DB_* env vars.');
        }

        $this->testSchema = 'test_auth_' . str_replace('-', '', (string) Str::uuid());

        $this->createIsolatedTestSchema();

        config([
            'auth.jwt.secret' => 'abcdefghijklmnopqrstuvwxyz123456',
            'auth.jwt.issuer' => 'https://serabutin.test',
            'auth.jwt.audience' => 'https://serabutin.test',
            'auth.jwt.access_ttl_seconds' => 900,
            'auth.refresh_token.ttl_seconds' => 86400,
            'auth.refresh_token.cookie.name' => 'refresh_token',
            'auth.refresh_token.cookie.path' => '/api/v1/auth',
            'auth.refresh_token.cookie.same_site' => 'lax',
            'auth.refresh_token.cookie.secure' => false,
            'auth.refresh_token.cookie.http_only' => true,
        ]);

        $this->prepareSchema();
    }

    protected function tearDown(): void
    {
        $this->dropIsolatedTestSchema();

        parent::tearDown();
    }

    public function test_register_creates_user_profile_and_sends_verification_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'register@example.com',
            'password' => 'secret12345',
            'full_name' => 'Register User',
            'role' => 'worker',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('auth.register.success'))
            ->assertJsonPath('data.email', 'register@example.com')
            ->assertJsonPath('data.is_verified', false);

        $user = User::query()->where('email', 'register@example.com')->first();

        $this->assertNotNull($user);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'avatar_url' => null,
        ]);

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail): bool {
            return $mail->hasTo('register@example.com');
        });
    }

    public function test_verify_marks_user_as_verified_for_valid_token(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'verify@example.com',
            'is_verified' => false,
        ]);

        $token = app(EmailVerificationTokenService::class)->issue($user);

        $response = $this->getJson('/api/v1/auth/verify?token=' . urlencode($token));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('auth.verify.success'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_verified' => 1,
        ]);
    }

    public function test_login_returns_access_token_and_refresh_cookie(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'login@example.com',
            'is_verified' => true,
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret12345',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('auth.login.success'))
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.user.id', $user->id);

        $cookie = $response->getCookie('refresh_token', false);

        $this->assertNotNull($cookie);
        $this->assertNotSame('', (string) $cookie->getValue());
    }

    public function test_login_rejects_unverified_user(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'unverified@example.com',
            'is_verified' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret12345',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('auth.login.email_not_verified'));
    }

    public function test_refresh_rotates_token_and_rejects_replay_of_old_refresh_token(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'refresh@example.com',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret12345',
        ]);

        $oldCookie = $loginResponse->getCookie('refresh_token', false);
        $this->assertNotNull($oldCookie);

        $oldRefreshToken = (string) $oldCookie->getValue();

        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $refreshResponse
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('auth.refresh.success'))
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 900);

        $newCookie = $refreshResponse->getCookie('refresh_token', false);
        $this->assertNotNull($newCookie);

        $newRefreshToken = (string) $newCookie->getValue();

        $this->assertNotSame($oldRefreshToken, $newRefreshToken);

        $refreshTokenService = app(RefreshTokenService::class);

        $this->assertDatabaseMissing('refresh_tokens', [
            'token_hash' => $refreshTokenService->hashToken($oldRefreshToken),
        ]);

        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => $refreshTokenService->hashToken($newRefreshToken),
            'user_id' => $user->id,
        ]);

        $replayResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $replayResponse
            ->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('auth.refresh.invalid_or_expired'));
    }

    public function test_logout_revokes_refresh_token_and_clears_cookie(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'logout@example.com',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret12345',
        ]);

        $accessToken = (string) $loginResponse->json('data.access_token');
        $refreshCookie = $loginResponse->getCookie('refresh_token', false);

        $this->assertNotNull($refreshCookie);

        $refreshToken = (string) $refreshCookie->getValue();

        $logoutResponse = $this
            ->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/v1/auth/logout', [
                'refresh_token' => $refreshToken,
            ]);

        $logoutResponse
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('auth.logout.success'));

        $refreshTokenService = app(RefreshTokenService::class);

        $this->assertDatabaseMissing('refresh_tokens', [
            'token_hash' => $refreshTokenService->hashToken($refreshToken),
        ]);

        $clearedCookie = $logoutResponse->getCookie('refresh_token', false);
        $this->assertNotNull($clearedCookie);
        $this->assertSame('', (string) $clearedCookie->getValue());
        $this->assertLessThan(time(), $clearedCookie->getExpiresTime());
    }

    public function test_logout_without_bearer_token_returns_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/auth/logout', [
            'refresh_token' => 'any-refresh-token',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('general.unauthenticated'));
    }

    public function test_login_limits_active_sessions_to_three_and_revokes_oldest(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'session-limit@example.com',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $refreshTokens = [];

        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'secret12345',
            ]);

            $response->assertOk();

            $cookie = $response->getCookie('refresh_token', false);
            $this->assertNotNull($cookie);

            $refreshTokens[] = (string) $cookie->getValue();
        }

        $refreshTokenService = app(RefreshTokenService::class);

        $this->assertDatabaseMissing('refresh_tokens', [
            'token_hash' => $refreshTokenService->hashToken($refreshTokens[0]),
            'user_id' => $user->id,
        ]);

        foreach (array_slice($refreshTokens, 1) as $token) {
            $this->assertDatabaseHas('refresh_tokens', [
                'token_hash' => $refreshTokenService->hashToken($token),
                'user_id' => $user->id,
            ]);
        }

        $this->assertSame(
            3,
            RefreshToken::query()->where('user_id', $user->id)->count()
        );
    }

    private function prepareSchema(): void
    {
        $schema = Schema::connection('pgsql');

        $schema->disableForeignKeyConstraints();
        $schema->dropIfExists('refresh_tokens');
        $schema->dropIfExists('user_profiles');
        $schema->dropIfExists('users');
        $schema->enableForeignKeyConstraints();

        $schema->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('full_name', 100);
            $table->enum('role', ['client', 'worker']);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('user_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('bio')->nullable();
            $table->string('location_district', 100)->nullable();
            $table->string('location_city', 100)->nullable();
            $table->string('avatar_url', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->float('avg_rating')->default(0);
            $table->integer('total_jobs_posted')->default(0);
            $table->integer('total_jobs_completed')->default(0);
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $schema->create('refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 255)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function configurePostgresConnection(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => env('TEST_DB_HOST', '127.0.0.1'),
            'database.connections.pgsql.port' => env('TEST_DB_PORT', '5432'),
            'database.connections.pgsql.database' => env('TEST_DB_DATABASE', 'serabutin_db'),
            'database.connections.pgsql.username' => env('TEST_DB_USERNAME', 'postgres'),
            'database.connections.pgsql.password' => env('TEST_DB_PASSWORD', 'postgres'),
            'database.connections.pgsql.search_path' => 'public',
        ]);

        DB::purge('pgsql');
    }

    private function canConnectToPostgres(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function createIsolatedTestSchema(): void
    {
        DB::connection('pgsql')->statement('CREATE SCHEMA "' . $this->testSchema . '"');

        config(['database.connections.pgsql.search_path' => $this->testSchema]);
        DB::purge('pgsql');
        DB::connection('pgsql')->getPdo();
    }

    private function dropIsolatedTestSchema(): void
    {
        if (!isset($this->testSchema) || $this->testSchema === '') {
            return;
        }

        try {
            config(['database.connections.pgsql.search_path' => 'public']);
            DB::purge('pgsql');
            DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS "' . $this->testSchema . '" CASCADE');
        } catch (Throwable) {
            // Keep teardown safe even when connection is unavailable.
        }
    }

}
