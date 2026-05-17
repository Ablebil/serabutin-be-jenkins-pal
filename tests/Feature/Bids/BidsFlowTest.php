<?php

namespace Tests\Feature\Bids;

use App\Models\JobAssignment;
use App\Models\User;
use App\Services\Auth\JwtService;
use Database\Factories\BidFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\JobFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Tests\TestCase;

class BidsFlowTest extends TestCase
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

        $this->testSchema = 'test_bids_' . str_replace('-', '', (string) Str::uuid());
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

    public function test_worker_can_create_bid(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $response = $this->withToken($this->getToken($this->worker))
            ->postJson('/api/v1/jobs/' . $job->id . '/bids', [
                'proposed_price' => 5000,
                'message' => 'Mohon dikerjakan sore hari jika memungkinkan.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.proposed_price', '5000.00')
            ->assertJsonPath('data.message', 'Mohon dikerjakan sore hari jika memungkinkan.');

        $this->assertDatabaseHas('bids', [
            'job_id' => $job->id,
            'worker_id' => $this->worker->id,
            'status' => 'pending',
            'proposed_price' => 5000.00,
            'message' => 'Mohon dikerjakan sore hari jika memungkinkan.',
        ]);
    }

    public function test_client_can_list_bids_for_own_job(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $this->worker->id,
        ]);

        BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $this->otherWorker->id,
        ]);

        $response = $this->withToken($this->getToken($this->client))
            ->getJson('/api/v1/jobs/' . $job->id . '/bids');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');
    }

    public function test_worker_can_cancel_bid(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
        ]);

        $bid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $this->worker->id,
            'status' => 'pending',
        ]);

        $response = $this->withToken($this->getToken($this->worker))
            ->deleteJson('/api/v1/bids/' . $bid->id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'withdrawn');

        $this->assertDatabaseHas('bids', [
            'id' => $bid->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_client_can_accept_bid_creates_assignment(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'workers_needed' => 1,
        ]);

        $bid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $this->worker->id,
            'status' => 'pending',
        ]);

        $response = $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/bids/' . $bid->id . '/accept');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('job_assignments', [
            'job_id' => $job->id,
            'bid_id' => $bid->id,
            'worker_id' => $this->worker->id,
            'client_id' => $this->client->id,
        ]);

        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->worker->id,
            'actor_id' => $this->client->id,
            'type' => \App\Notifications\BidAcceptedNotification::class,
        ]);

        $notificationResponse = $this->withToken($this->getToken($this->worker))
            ->getJson('/api/v1/notifications');

        $notificationResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job_id', $job->id)
            ->assertJsonPath('data.0.job_title', $job->title)
            ->assertJsonPath('data.0.bid_id', $bid->id)
            ->assertJsonPath('data.0.is_read', false);

        expect($notificationResponse->json('meta.unread_count'))->toBe(1);
    }

    public function test_client_accepting_final_slot_auto_rejects_other_pending_bids(): void
    {
        $job = JobFactory::new()->create([
            'client_id' => $this->client->id,
            'category_id' => $this->categoryId,
            'status' => 'open',
            'workers_needed' => 2,
        ]);

        $firstBid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $this->worker->id,
            'status' => 'pending',
        ]);

        $secondBid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $this->otherWorker->id,
            'status' => 'pending',
        ]);

        $thirdWorker = UserFactory::new()->create(['role' => 'worker']);
        $thirdBid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $thirdWorker->id,
            'status' => 'pending',
        ]);

        $firstResponse = $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/bids/' . $firstBid->id . '/accept');

        $firstResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $secondResponse = $this->withToken($this->getToken($this->client))
            ->patchJson('/api/v1/bids/' . $secondBid->id . '/accept');

        $secondResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('job_assignments', [
            'job_id' => $job->id,
            'bid_id' => $firstBid->id,
            'worker_id' => $this->worker->id,
            'client_id' => $this->client->id,
        ]);

        $this->assertDatabaseHas('job_assignments', [
            'job_id' => $job->id,
            'bid_id' => $secondBid->id,
            'worker_id' => $this->otherWorker->id,
            'client_id' => $this->client->id,
        ]);

        $this->assertDatabaseHas('bids', [
            'id' => $thirdBid->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'in_progress',
        ]);
    }
}
