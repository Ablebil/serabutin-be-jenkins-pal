<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Services\Auth\JwtService;
use Database\Factories\BidFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\JobAssignmentFactory;
use Database\Factories\JobFactory;
use Database\Factories\ReviewFactory;
use Database\Factories\UserFactory;
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

    private function getHeaders(User $user): array
    {
        $token = app(JwtService::class)->issueAccessToken($user)['access_token'];
        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function test_get_me_returns_profile()
    {
        $user = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $response = $this->getJson('/api/v1/users/me', $this->getHeaders($user));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.profile.bio', 'Test Bio');
    }

    public function test_update_me_modifies_profile()
    {
        $user = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);

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

    public function test_update_me_allows_phone_for_client()
    {
        $user = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);

        $response = $this->patchJson('/api/v1/users/me', [
            'phone' => '08999999999',
        ], $this->getHeaders($user));

        $response->assertOk();
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'phone' => '08999999999']);
    }

    public function test_update_me_ignores_phone_for_worker()
    {
        $user = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'worker']);

        $response = $this->patchJson('/api/v1/users/me', [
            'phone' => '08999999999',
        ], $this->getHeaders($user));

        $response->assertOk();
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'phone' => '081234567890']);
    }

    public function test_me_jobs_returns_posted_jobs_for_client()
    {
        $client = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $category = CategoryFactory::new()->create();

        $reviewedJob = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $reviewedBid = BidFactory::new()->create([
            'job_id' => $reviewedJob->id,
            'worker_id' => UserFactory::new()->create(['role' => 'worker'])->id,
        ]);

        $reviewedAssignment = JobAssignmentFactory::new()->create([
            'client_id' => $client->id,
            'job_id' => $reviewedJob->id,
            'worker_id' => $reviewedBid->worker_id,
            'bid_id' => $reviewedBid->id,
        ]);

        ReviewFactory::new()->create([
            'assignment_id' => $reviewedAssignment->id,
            'reviewer_id' => $client->id,
            'reviewee_id' => $reviewedBid->worker_id,
            'rating' => 5,
        ]);

        $pendingJob = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $pendingBid = BidFactory::new()->create([
            'job_id' => $pendingJob->id,
            'worker_id' => UserFactory::new()->create(['role' => 'worker'])->id,
        ]);

        $pendingAssignment = JobAssignmentFactory::new()->create([
            'client_id' => $client->id,
            'job_id' => $pendingJob->id,
            'worker_id' => $pendingBid->worker_id,
            'bid_id' => $pendingBid->id,
        ]);

        $response = $this->getJson('/api/v1/users/me/jobs', $this->getHeaders($client));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');

        $jobs = collect($response->json('data'))->keyBy('id');

        expect(array_key_exists('has_reviewed', $jobs->get($reviewedJob->id)))->toBeFalse();
        expect(array_key_exists('has_reviewed', $jobs->get($pendingJob->id)))->toBeFalse();
        expect($jobs->get($reviewedJob->id)['assignments'][0]['assignment_id'])->toBe($reviewedAssignment->id);
        expect(array_key_exists('job', $jobs->get($reviewedJob->id)['assignments'][0]))->toBeFalse();
        expect($jobs->get($reviewedJob->id)['assignments'][0]['has_reviewed'])->toBeTrue();
        expect($jobs->get($pendingJob->id)['assignments'][0]['assignment_id'])->toBe($pendingAssignment->id);
        expect(array_key_exists('job', $jobs->get($pendingJob->id)['assignments'][0]))->toBeFalse();
        expect($jobs->get($pendingJob->id)['assignments'][0]['has_reviewed'])->toBeFalse();
    }

    public function test_me_jobs_forbidden_for_worker()
    {
        $worker = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'worker']);
        $response = $this->getJson('/api/v1/users/me/jobs', $this->getHeaders($worker));
        $response->assertStatus(403);
    }

    public function test_me_bids_returns_bids_for_worker()
    {
        $worker = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'worker']);
        $client = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $category = CategoryFactory::new()->create();

        $job = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);

        BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'status' => 'accepted',
        ]);

        $response = $this->getJson('/api/v1/users/me/bids', $this->getHeaders($worker));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client.phone', '081234567890');
    }

    public function test_me_bids_forbidden_for_client()
    {
        $client = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $response = $this->getJson('/api/v1/users/me/bids', $this->getHeaders($client));
        $response->dump();
        $response->assertStatus(403);
    }

    public function test_me_assignments_returns_assignments_for_worker()
    {
        $worker = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'worker']);
        $client = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $category = CategoryFactory::new()->create();

        $reviewedJob = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $reviewedBid = BidFactory::new()->create([
            'job_id' => $reviewedJob->id,
            'worker_id' => $worker->id,
        ]);

        $reviewedAssignment = JobAssignmentFactory::new()->create([
            'client_id' => $client->id,
            'job_id' => $reviewedJob->id,
            'worker_id' => $worker->id,
            'bid_id' => $reviewedBid->id,
        ]);

        ReviewFactory::new()->create([
            'assignment_id' => $reviewedAssignment->id,
            'reviewer_id' => $worker->id,
            'reviewee_id' => $client->id,
            'rating' => 5,
        ]);

        $pendingJob = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $pendingBid = BidFactory::new()->create([
            'job_id' => $pendingJob->id,
            'worker_id' => $worker->id,
        ]);

        $pendingAssignment = JobAssignmentFactory::new()->create([
            'client_id' => $client->id,
            'job_id' => $pendingJob->id,
            'worker_id' => $worker->id,
            'bid_id' => $pendingBid->id,
        ]);

        $response = $this->getJson('/api/v1/users/me/assignments', $this->getHeaders($worker));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');

        $assignments = collect($response->json('data'))->keyBy('assignment_id');

        expect($assignments->get($reviewedAssignment->id)['job']['id'])->toBe($reviewedJob->id);
        expect($assignments->get($reviewedAssignment->id)['has_reviewed'])->toBeTrue();
        expect($assignments->get($pendingAssignment->id)['job']['id'])->toBe($pendingJob->id);
        expect($assignments->get($pendingAssignment->id)['has_reviewed'])->toBeFalse();
    }

    public function test_me_assignments_forbidden_for_client()
    {
        $client = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $response = $this->getJson('/api/v1/users/me/assignments', $this->getHeaders($client));
        $response->assertStatus(403);
    }

    public function test_me_reviews_returns_reviews()
    {
        $reviewee = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'worker']);
        $reviewer = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);

        $category = CategoryFactory::new()->create();

        $job = JobFactory::new()->create([
            'client_id' => $reviewer->id,
            'category_id' => $category->id,
        ]);
        $bid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $reviewee->id,
        ]);
        $assignment = JobAssignmentFactory::new()->create([
            'client_id' => $reviewer->id,
            'job_id' => $job->id,
            'worker_id' => $reviewee->id,
            'bid_id' => $bid->id,
        ]);

        ReviewFactory::new()->create([
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
        $user = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);

        $response = $this->getJson('/api/v1/users/' . $user->id);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.profile.phone', null); // Phone hidden by default
    }

    public function test_public_profile_shows_client_phone_to_assigned_worker()
    {
        $client = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
            'phone' => '081234567890',
        ])->create(['role' => 'client']);
        $worker = UserFactory::new()->withProfile([
            'bio' => 'Test Bio',
            'location_district' => 'Lowokwaru',
            'location_city' => 'Kota Malang',
        ])->create(['role' => 'worker']);

        $category = CategoryFactory::new()->create();

        $job = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);
        $bid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
        ]);
        JobAssignmentFactory::new()->create([
            'client_id' => $client->id,
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'bid_id' => $bid->id,
        ]);

        $response = $this->getJson('/api/v1/users/' . $client->id, $this->getHeaders($worker));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $client->id)
            ->assertJsonPath('data.profile.phone', '081234567890');
    }
}
