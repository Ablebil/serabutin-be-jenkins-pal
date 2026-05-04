<?php

namespace Tests\Feature\Uploads;

use App\Models\User;
use App\Services\Auth\JwtService;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class UploadsFlowTest extends TestCase
{
    private string $testSchema;
    private string $diskName;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_pgsql extension is required for uploads feature tests.');
        }

        $this->configurePostgresConnection();

        if (!$this->canConnectToPostgres()) {
            $this->markTestSkipped('PostgreSQL test connection is not available. Configure TEST_DB_* env vars.');
        }

        $this->testSchema = 'test_uploads_' . str_replace('-', '', (string) Str::uuid());
        $this->createIsolatedTestSchema();

        config([
            'auth.jwt.secret' => 'abcdefghijklmnopqrstuvwxyz123456',
            'auth.jwt.issuer' => 'https://serabutin.test',
            'auth.jwt.audience' => 'https://serabutin.test',
            'auth.jwt.access_ttl_seconds' => 900,
        ]);

        $this->artisan('migrate:fresh');

        $this->diskName = config('filesystems.default');
        Storage::fake($this->diskName);
    }

    protected function tearDown(): void
    {
        $this->dropIsolatedTestSchema();
        parent::tearDown();
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
        }
    }

    private function getHeaders(User $user): array
    {
        $token = app(JwtService::class)->issueAccessToken($user)['access_token'];

        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function test_upload_avatar_returns_url(): void
    {
        $user = UserFactory::new()->withProfile()->create(['role' => 'worker']);
        $file = UploadedFile::fake()->image('avatar.jpg', 256, 256);

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
        ], $this->getHeaders($user));

        $response
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('uploads.upload.success'))
            ->assertJsonStructure(['data' => ['url']]);

        $url = (string) $response->json('data.url');
        $this->assertNotSame('', $url);

        $path = parse_url($url, PHP_URL_PATH);
        $path = preg_replace('#^/storage/#', '', $path);

        Storage::assertExists($path);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        $user = UserFactory::new()->withProfile()->create(['role' => 'worker']);
        $file = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
        ], $this->getHeaders($user));

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', __('uploads.validation.file_unsupported'));
    }

    public function test_upload_rejects_large_files(): void
    {
        $user = UserFactory::new()->withProfile()->create(['role' => 'worker']);
        $file = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
        ], $this->getHeaders($user));

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', __('uploads.validation.file_too_large'));
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 256, 256);

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', __('general.unauthenticated'));
    }
}
