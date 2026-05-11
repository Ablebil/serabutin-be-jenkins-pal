<?php

namespace Tests\Feature\Reviews;

use App\Models\Job;
use App\Models\JobAssignment;
use App\Models\User;
use App\Services\Auth\JwtService;
use Database\Factories\BidFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\JobAssignmentFactory;
use Database\Factories\JobFactory;
use Database\Factories\ReviewFactory;
use Database\Factories\UserFactory;
use Database\Factories\UserProfileFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Tests\TestCase;

class ReviewsFlowTest extends TestCase
{
    private string $testSchema;
    private JwtService $jwt;
    private User $client;
    private User $worker;
    private User $otherWorker;
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

        $this->testSchema = 'test_reviews_' . str_replace('-', '', (string) Str::uuid());
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
        $this->otherWorker = UserFactory::new()->create(['role' => 'worker']);

        UserProfileFactory::new()->create(['user_id' => $this->client->id, 'avg_rating' => 0]);
        UserProfileFactory::new()->create(['user_id' => $this->worker->id, 'avg_rating' => 0]);
        UserProfileFactory::new()->create(['user_id' => $this->otherWorker->id, 'avg_rating' => 0]);

        $category = CategoryFactory::new()->create();
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

    private function createAssignment(Job $job, User $worker): JobAssignment
    {
        $bid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
            'status' => 'accepted',
        ]);

        return JobAssignmentFactory::new()->create([
            'job_id' => $job->id,
            'bid_id' => $bid->id,
            'worker_id' => $worker->id,
            'client_id' => $job->client_id,
        ]);
    }

    public function test_client_can_submit_review_for_worker(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'completed',
        ]);

        $assignment = $this->createAssignment($job, $this->worker);

        $response = $this->withToken($this->getToken($this->client))
            ->postJson('/api/v1/jobs/' . $job->id . '/reviews', [
                'assignment_id' => $assignment->id,
                'rating' => 4,
                'comment' => 'Mantap',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.rating', 4);

        $this->assertDatabaseHas('reviews', [
            'assignment_id' => $assignment->id,
            'reviewer_id' => $this->client->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 4,
        ]);

        $avgRating = (float) DB::table('user_profiles')
            ->where('user_id', $this->worker->id)
            ->value('avg_rating');

        $this->assertEquals(4.0, $avgRating);
    }

    public function test_worker_can_submit_review_for_client(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'completed',
        ]);

        $assignment = $this->createAssignment($job, $this->worker);

        $response = $this->withToken($this->getToken($this->worker))
            ->postJson('/api/v1/jobs/' . $job->id . '/reviews', [
                'assignment_id' => $assignment->id,
                'rating' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'assignment_id' => $assignment->id,
            'reviewer_id' => $this->worker->id,
            'reviewee_id' => $this->client->id,
            'rating' => 5,
        ]);

        $avgRating = (float) DB::table('user_profiles')
            ->where('user_id', $this->client->id)
            ->value('avg_rating');

        $this->assertEquals(5.0, $avgRating);
    }

    public function test_review_requires_completed_job(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $assignment = $this->createAssignment($job, $this->worker);

        $response = $this->withToken($this->getToken($this->client))
            ->postJson('/api/v1/jobs/' . $job->id . '/reviews', [
                'assignment_id' => $assignment->id,
                'rating' => 3,
            ]);

        $response->assertStatus(403);
    }

    public function test_worker_cannot_review_with_other_assignment(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'completed',
        ]);

        $assignment = $this->createAssignment($job, $this->worker);

        $response = $this->withToken($this->getToken($this->otherWorker))
            ->postJson('/api/v1/jobs/' . $job->id . '/reviews', [
                'assignment_id' => $assignment->id,
                'rating' => 4,
            ]);

        $response->assertStatus(404);
    }

    public function test_review_rejects_duplicates(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'completed',
        ]);

        $assignment = $this->createAssignment($job, $this->worker);

        ReviewFactory::new()->create([
            'assignment_id' => $assignment->id,
            'reviewer_id' => $this->client->id,
            'reviewee_id' => $this->worker->id,
            'rating' => 4,
        ]);

        $response = $this->withToken($this->getToken($this->client))
            ->postJson('/api/v1/jobs/' . $job->id . '/reviews', [
                'assignment_id' => $assignment->id,
                'rating' => 4,
            ]);

        $response->assertStatus(409);
    }
}
