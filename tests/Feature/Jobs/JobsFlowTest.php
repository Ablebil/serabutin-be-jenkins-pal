<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use App\Services\Auth\JwtService;
use Database\Factories\CategoryFactory;
use Database\Factories\JobFactory;
use Database\Factories\UserFactory;
use Database\Factories\UserProfileFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Tests\TestCase;

class JobsFlowTest extends TestCase
{
    private string $testSchema;
    private JwtService $jwt;
    private User $client;
    private User $worker;
    private User $otherClient;
    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_pgsql extension is required for feature tests.');
        }

        $this->configurePostgresConnection();

        if (!$this->canConnectToPostgres()) {
            $this->markTestSkipped('PostgreSQL test connection is not available. Configure TEST_DB_* env vars.');
        }

        $this->testSchema = 'test_jobs_' . str_replace('-', '', (string) Str::uuid());
        $this->createIsolatedTestSchema();

        config([
            'auth.jwt.secret' => 'abcdefghijklmnopqrstuvwxyz123456',
            'auth.jwt.issuer' => 'https://serabutin.test',
            'auth.jwt.audience' => 'https://serabutin.test',
            'auth.jwt.access_ttl_seconds' => 900,
        ]);

        $this->artisan('migrate:fresh');

        $this->jwt = new JwtService();

        $this->client = UserFactory::new()->create(['role' => 'client']);
        $this->worker = UserFactory::new()->create(['role' => 'worker']);
        $this->otherClient = UserFactory::new()->create(['role' => 'client']);

        UserProfileFactory::new()->create([
            'user_id' => $this->client->id,
            'total_jobs_posted' => 0,
        ]);
        UserProfileFactory::new()->create([
            'user_id' => $this->worker->id,
            'total_jobs_completed' => 0,
        ]);

        $category = CategoryFactory::new()->create([
            'name' => 'Bebersih',
            'slug' => 'bebersih',
            'is_active' => true,
        ]);
        $this->categoryId = $category->id;
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

    private function getToken(User $user): string
    {
        return $this->jwt->issueAccessToken($user)['access_token'];
    }

    public function test_feed_returns_open_jobs_with_cursor_pagination(): void
    {
        JobFactory::new()->count(15)->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'in_progress',
        ]);

        $response = $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?limit=10');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        expect($response->json('meta.has_more'))->toBeTrue();
        expect($response->json('meta.next_cursor'))->not->toBeNull();
        expect(count($response->json('data')))->toBe(10);
    }

    public function test_feed_filters_by_category_slug(): void
    {
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?category_slug=bebersih')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_feed_filters_by_city(): void
    {
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'location_city' => 'Kota Malang',
        ]);
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'location_city' => 'Kota Batu',
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?city=Kota Malang')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/v1/jobs')->assertStatus(401);
    }

    public function test_store_creates_job_for_client(): void
    {
        $payload = [
            'category_id' => $this->categoryId,
            'title' => 'Butuh tukang bebersih',
            'description' => 'Rumah 2 lantai',
            'budget_min' => 80000,
            'budget_max' => 150000,
            'workers_needed' => 2,
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i:s\Z'),
            'deadline_at' => now()->addDays(7)->format('Y-m-d\TH:i:s\Z'),
        ];

        $response = $this->withToken($this->getToken($this->client))
            ->postJson('/api/v1/jobs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Butuh tukang bebersih')
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('jobs', [
            'client_id' => $this->client->id,
            'title' => 'Butuh tukang bebersih',
        ]);
    }

    public function test_store_rejects_non_client(): void
    {
        $payload = [
            'category_id' => $this->categoryId,
            'title' => 'Test job',
            'description' => 'Test description',
            'budget_min' => 50000,
            'budget_max' => 100000,
            'workers_needed' => 1,
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'start_at' => '2025-05-01T08:00:00Z',
            'deadline_at' => '2025-05-01T17:00:00Z',
        ];

        $this->withToken($this->getToken($this->worker))
            ->postJson('/api/v1/jobs', $payload)
            ->assertStatus(403);
    }

    public function test_store_validates_budget_min_max(): void
    {
        $payload = [
            'category_id' => $this->categoryId,
            'title' => 'Test job',
            'description' => 'Test description',
            'budget_min' => 150000,
            'budget_max' => 100000,
            'workers_needed' => 1,
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'start_at' => '2025-05-01T08:00:00Z',
            'deadline_at' => '2025-05-01T17:00:00Z',
        ];

        $this->withToken($this->getToken($this->client))
            ->postJson('/api/v1/jobs', $payload)
            ->assertStatus(422);
    }

    public function test_show_returns_job_detail(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs/' . $job->id)
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $job->id);
    }

    public function test_show_returns_404_for_deleted_job(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);
        $job->delete();

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs/' . $job->id)
            ->assertStatus(404);
    }

    public function test_update_modifies_job_for_owner(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'title' => 'Old title',
        ]);

        $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/jobs/' . $job->id, ['title' => 'New title'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'New title');
    }

    public function test_update_rejects_non_owner(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $this->withToken($this->getToken($this->otherClient))
            ->patchJson('/api/v1/jobs/' . $job->id, ['title' => 'New title'])
            ->assertStatus(403);
    }

    public function test_update_rejects_non_open_job(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'in_progress',
        ]);

        $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/jobs/' . $job->id, ['title' => 'New title'])
            ->assertStatus(403);
    }

    public function test_destroy_deletes_job_for_owner(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $this->withToken($this->getToken($this->client))
            ->deleteJson('/api/v1/jobs/' . $job->id)
            ->assertStatus(200);

        $job->refresh();
        expect($job->deleted_at)->not->toBeNull();
    }

    public function test_destroy_rejects_non_owner(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $this->withToken($this->getToken($this->otherClient))
            ->deleteJson('/api/v1/jobs/' . $job->id)
            ->assertStatus(403);
    }

    public function test_update_status_changes_to_completed(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'in_progress',
        ]);

        $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/jobs/' . $job->id . '/status', ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_update_status_rejects_non_client(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'in_progress',
        ]);

        $this->withToken($this->getToken($this->worker))
            ->patchJson('/api/v1/jobs/' . $job->id . '/status', ['status' => 'completed'])
            ->assertStatus(403);
    }

    public function test_feed_filters_by_budget_min(): void
    {
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'budget_max' => 100000,
        ]);
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'budget_max' => 50000,
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?budget_min=60000')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_feed_filters_by_budget_max(): void
    {
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'budget_min' => 80000,
        ]);
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'budget_min' => 120000,
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?budget_max=100000')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_feed_filters_by_date_from(): void
    {
        $yesterday = now()->subDay();
        $tomorrow = now()->addDay();

        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'created_at' => $yesterday,
        ]);
        $newJob = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'created_at' => $tomorrow,
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?date_from=' . $tomorrow->format('Y-m-d'))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newJob->id);
    }

    public function test_feed_filters_by_date_to(): void
    {
        $yesterday = now()->subDay();
        $tomorrow = now()->addDay();

        $oldJob = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'created_at' => $yesterday,
        ]);
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'created_at' => $tomorrow,
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?date_to=' . $yesterday->format('Y-m-d'))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $oldJob->id);
    }

    public function test_feed_full_text_search(): void
    {
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'title' => 'Butuh tukang bebersih rumah',
        ]);
        JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'title' => 'Servis AC ruangan',
        ]);

        $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs?q=bebersih')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Butuh tukang bebersih rumah');
    }

    public function test_store_increments_total_jobs_posted(): void
    {
        $payload = [
            'category_id' => $this->categoryId,
            'title' => 'Test job for profile sync',
            'description' => 'Test description',
            'budget_min' => 50000,
            'budget_max' => 100000,
            'workers_needed' => 1,
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i:s\Z'),
            'deadline_at' => now()->addDays(7)->format('Y-m-d\TH:i:s\Z'),
        ];

        $this->withToken($this->getToken($this->client))
            ->postJson('/api/v1/jobs', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->client->id,
            'total_jobs_posted' => 1,
        ]);
    }

    public function test_update_status_completed_increments_total_jobs_completed(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'in_progress',
        ]);

        \App\Models\JobAssignment::create([
            'job_id' => $job->id,
            'worker_id' => $this->worker->id,
            'client_id' => $this->client->id,
            'bid_id' => \Database\Factories\BidFactory::new()->create([
                'job_id' => $job->id,
                'worker_id' => $this->worker->id,
            ])->id,
        ]);

        $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/jobs/' . $job->id . '/status', ['status' => 'completed'])
            ->assertStatus(200);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->worker->id,
            'total_jobs_completed' => 1,
        ]);
    }
}
