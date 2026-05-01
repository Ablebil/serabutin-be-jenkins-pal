<?php

namespace Tests\Feature\Users;

use App\Models\Bid;
use App\Models\Category;
use App\Models\Job;
use App\Models\JobAssignment;
use App\Models\Review;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Tests\TestCase;

class UsersFlowTest extends TestCase
{
    private string $testSchema;

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

        $this->testSchema = 'test_users_' . str_replace('-', '', (string) Str::uuid());
        $this->createIsolatedTestSchema();

        config([
            'auth.jwt.secret' => 'abcdefghijklmnopqrstuvwxyz123456',
            'auth.jwt.issuer' => 'https://serabutin.test',
            'auth.jwt.audience' => 'https://serabutin.test',
            'auth.jwt.access_ttl_seconds' => 900,
        ]);

        $this->artisan('migrate:fresh');
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

    private function createUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'email' => 'user_' . Str::random(10) . '@example.com',
            'full_name' => 'User Name',
            'role' => 'worker',
            'password_hash' => 'secret12345',
            'is_verified' => true,
            'is_active' => true,
        ], $attributes));

        $user->profile()->create([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ]);

        return $user;
    }

    private function getHeaders(User $user): array
    {
        $token = app(JwtService::class)->issueAccessToken($user)['access_token'];
        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function test_get_me_returns_profile()
    {
        $user = $this->createUser(['role' => 'client']);
        $response = $this->getJson('/api/v1/users/me', $this->getHeaders($user));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.profile.bio', 'Test Bio');
    }

    public function test_update_me_modifies_profile()
    {
        $user = $this->createUser(['role' => 'worker']);

        $response = $this->patchJson('/api/v1/users/me', [
            'full_name' => 'Updated Name',
            'bio' => 'Updated Bio',
            'phone' => '08999999999',
        ], $this->getHeaders($user));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.full_name', 'Updated Name')
            ->assertJsonPath('data.profile.bio', 'Updated Bio')
            ->assertJsonPath('data.profile.phone', '08999999999');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'full_name' => 'Updated Name']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'bio' => 'Updated Bio', 'phone' => '08999999999']);
    }

    public function test_update_me_ignores_phone_for_client()
    {
        $user = $this->createUser(['role' => 'client']);

        $response = $this->patchJson('/api/v1/users/me', [
            'phone' => '08999999999',
        ], $this->getHeaders($user));

        $response->assertOk();
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'phone' => '081234567890']);
    }

    public function test_me_jobs_returns_posted_jobs_for_client()
    {
        $client = $this->createUser(['role' => 'client']);
        $category = Category::query()->create([
            'name' => 'Category Name',
            'slug' => 'category-slug',
        ]);

        Job::query()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
            'title' => 'Job 1',
            'description' => 'Desc',
            'budget_min' => 1000,
            'budget_max' => 2000,
            'workers_needed' => 1,
            'location_district' => 'Dist',
            'location_city' => 'City',
            'status' => 'open',
            'start_at' => now(),
            'deadline_at' => now()->addDays(7),
        ]);

        Job::query()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
            'title' => 'Job 2',
            'description' => 'Desc',
            'budget_min' => 1000,
            'budget_max' => 2000,
            'workers_needed' => 1,
            'location_district' => 'Dist',
            'location_city' => 'City',
            'status' => 'open',
            'start_at' => now(),
            'deadline_at' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/v1/users/me/jobs', $this->getHeaders($client));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');
    }

    public function test_me_jobs_forbidden_for_worker()
    {
        $worker = $this->createUser(['role' => 'worker']);
        $response = $this->getJson('/api/v1/users/me/jobs', $this->getHeaders($worker));
        $response->assertStatus(403);
    }

    public function test_me_bids_returns_bids_for_worker()
    {
        $worker = $this->createUser(['role' => 'worker']);
        $client = $this->createUser(['role' => 'client']);
        $category = Category::query()->create([
            'name' => 'Category Name',
            'slug' => 'category-slug',
        ]);

        $job = Job::query()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
            'title' => 'Job 1',
            'description' => 'Desc',
            'budget_min' => 1000,
            'budget_max' => 2000,
            'workers_needed' => 1,
            'location_district' => 'Dist',
            'location_city' => 'City',
            'status' => 'open',
            'start_at' => now(),
            'deadline_at' => now()->addDays(7),
        ]);

        Bid::query()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'proposed_price' => 1500,
            'estimated_duration_hours' => 2,
            'message' => 'Hire me',
        ]);

        $response = $this->getJson('/api/v1/users/me/bids', $this->getHeaders($worker));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_me_bids_forbidden_for_client()
    {
        $client = $this->createUser(['role' => 'client']);
        $response = $this->getJson('/api/v1/users/me/bids', $this->getHeaders($client));
        $response->dump();
        $response->assertStatus(403);
    }

    public function test_me_assignments_returns_assignments_for_worker()
    {
        $worker = $this->createUser(['role' => 'worker']);
        $client = $this->createUser(['role' => 'client']);
        $category = Category::query()->create([
            'name' => 'Category Name',
            'slug' => 'category-slug',
        ]);

        $job = Job::query()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
            'title' => 'Job 1',
            'description' => 'Desc',
            'budget_min' => 1000,
            'budget_max' => 2000,
            'workers_needed' => 1,
            'location_district' => 'Dist',
            'location_city' => 'City',
            'status' => 'open',
            'start_at' => now(),
            'deadline_at' => now()->addDays(7),
        ]);

        $bid = Bid::query()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'proposed_price' => 1500,
            'estimated_duration_hours' => 2,
            'message' => 'Hire me',
        ]);

        JobAssignment::query()->create([
            'client_id' => $client->id,
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'bid_id' => $bid->id,
            'agreed_price' => 1500,
        ]);

        $response = $this->getJson('/api/v1/users/me/assignments', $this->getHeaders($worker));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_me_assignments_forbidden_for_client()
    {
        $client = $this->createUser(['role' => 'client']);
        $response = $this->getJson('/api/v1/users/me/assignments', $this->getHeaders($client));
        $response->assertStatus(403);
    }

    public function test_me_reviews_returns_reviews()
    {
        $reviewee = $this->createUser(['role' => 'worker']);
        $reviewer = $this->createUser(['role' => 'client']);

        $category = Category::query()->create([
            'name' => 'Category Name',
            'slug' => 'category-slug',
        ]);

        $job = Job::query()->create([
            'client_id' => $reviewer->id,
            'category_id' => $category->id,
            'title' => 'Job 1',
            'description' => 'Desc',
            'budget_min' => 1000,
            'budget_max' => 2000,
            'workers_needed' => 1,
            'location_district' => 'Dist',
            'location_city' => 'City',
            'status' => 'open',
            'start_at' => now(),
            'deadline_at' => now()->addDays(7),
        ]);
        $bid = Bid::query()->create([
            'job_id' => $job->id,
            'worker_id' => $reviewee->id,
            'proposed_price' => 1000,
            'estimated_duration_hours' => 2,
            'message' => 'Hire me',
        ]);
        $assignment = JobAssignment::query()->create(['client_id' => $reviewer->id, 'job_id' => $job->id, 'worker_id' => $reviewee->id, 'bid_id' => $bid->id, 'agreed_price' => 1000]);

        Review::query()->create([
            'assignment_id' => $assignment->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
            'rating' => 5,
        ]);

        $response = $this->getJson('/api/v1/users/me/reviews', $this->getHeaders($reviewee));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_public_profile_returns_basic_info_without_auth()
    {
        $user = $this->createUser(['role' => 'client']);

        $response = $this->getJson('/api/v1/users/' . $user->id);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.profile.phone', null); // Phone hidden by default
    }

    public function test_public_profile_shows_worker_phone_to_assigned_client()
    {
        $worker = $this->createUser(['role' => 'worker']);
        $client = $this->createUser(['role' => 'client']);

        $category = Category::query()->create([
            'name' => 'Category Name',
            'slug' => 'category-slug',
        ]);

        $job = Job::query()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
            'title' => 'Job 1',
            'description' => 'Desc',
            'budget_min' => 1000,
            'budget_max' => 2000,
            'workers_needed' => 1,
            'location_district' => 'Dist',
            'location_city' => 'City',
            'status' => 'open',
            'start_at' => now(),
            'deadline_at' => now()->addDays(7),
        ]);
        $bid = Bid::query()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'proposed_price' => 1500,
            'estimated_duration_hours' => 2,
            'message' => 'Hire me',
        ]);
        JobAssignment::query()->create(['client_id' => $client->id, 'job_id' => $job->id, 'worker_id' => $worker->id, 'bid_id' => $bid->id, 'agreed_price' => 1500]);

        $response = $this->getJson('/api/v1/users/' . $worker->id, $this->getHeaders($client));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $worker->id)
            ->assertJsonPath('data.profile.phone', '081234567890');
    }
}
