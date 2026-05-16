<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Services\Auth\JwtService;
use Database\Factories\BidFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\JobFactory;
use Database\Factories\NotificationFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Tests\TestCase;

class NotificationsFlowTest extends TestCase
{
    private string $testSchema;
    private JwtService $jwt;

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

        $this->testSchema = 'test_notifications_' . str_replace('-', '', (string) Str::uuid());
        $this->createIsolatedTestSchema();

        config([
            'auth.jwt.secret' => 'abcdefghijklmnopqrstuvwxyz123456',
            'auth.jwt.issuer' => 'https://serabutin.test',
            'auth.jwt.audience' => 'https://serabutin.test',
            'auth.jwt.access_ttl_seconds' => 900,
        ]);

        $this->artisan('migrate:fresh');

        $this->jwt = new JwtService();
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

    private function createJobWithBid(User $worker): array
    {
        $client = UserFactory::new()->create(['role' => 'client']);
        $category = CategoryFactory::new()->create();

        $job = JobFactory::new()->create([
            'client_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $bid = BidFactory::new()->create([
            'job_id' => $job->id,
            'worker_id' => $worker->id,
        ]);

        return [$job, $bid];
    }

    public function test_list_notifications_returns_worker_notifications_sorted_by_newest(): void
    {
        $worker = UserFactory::new()->create(['role' => 'worker']);

        [$jobOld, $bidOld] = $this->createJobWithBid($worker);
        $oldNotification = NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobOld->id,
                'job_title' => $jobOld->title,
                'bid_id' => $bidOld->id,
            ],
            'read_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        [$jobNew, $bidNew] = $this->createJobWithBid($worker);
        $newNotification = NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobNew->id,
                'job_title' => $jobNew->title,
                'bid_id' => $bidNew->id,
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherWorker = UserFactory::new()->create(['role' => 'worker']);
        [$jobOther, $bidOther] = $this->createJobWithBid($otherWorker);
        NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $otherWorker->id,
            'data' => [
                'job_id' => $jobOther->id,
                'job_title' => $jobOther->title,
                'bid_id' => $bidOther->id,
            ],
            'read_at' => null,
        ]);

        $response = $this->withToken($this->getToken($worker))
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newNotification->id)
            ->assertJsonPath('data.1.id', $oldNotification->id);

        expect($response->json('meta.unread_count'))->toBe(2);
    }

    public function test_list_notifications_filters_by_is_read(): void
    {
        $worker = UserFactory::new()->create(['role' => 'worker']);

        [$jobUnread, $bidUnread] = $this->createJobWithBid($worker);
        $unread = NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobUnread->id,
                'job_title' => $jobUnread->title,
                'bid_id' => $bidUnread->id,
            ],
            'read_at' => null,
        ]);

        [$jobRead, $bidRead] = $this->createJobWithBid($worker);
        $read = NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobRead->id,
                'job_title' => $jobRead->title,
                'bid_id' => $bidRead->id,
            ],
            'read_at' => now(),
        ]);

        $response = $this->withToken($this->getToken($worker))
            ->getJson('/api/v1/notifications?is_read=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $read->id);

        expect($response->json('meta.unread_count'))->toBe(1);
        expect($response->json('data.0.id'))->not->toBe($unread->id);
    }

    public function test_list_notifications_forbidden_for_client(): void
    {
        $client = UserFactory::new()->create(['role' => 'client']);

        $response = $this->withToken($this->getToken($client))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(403);
    }

    public function test_mark_notification_as_read(): void
    {
        $worker = UserFactory::new()->create(['role' => 'worker']);

        [$job, $bid] = $this->createJobWithBid($worker);
        $notification = NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $job->id,
                'job_title' => $job->title,
                'bid_id' => $bid->id,
            ],
            'read_at' => null,
        ]);

        $response = $this->withToken($this->getToken($worker))
            ->patchJson('/api/v1/notifications/' . $notification->id . '/read');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_read', true);

        $notification->refresh();
        expect($notification->read_at)->not->toBeNull();
    }

    public function test_mark_notification_as_read_forbidden_for_other_user(): void
    {
        $worker = UserFactory::new()->create(['role' => 'worker']);
        $otherWorker = UserFactory::new()->create(['role' => 'worker']);

        [$job, $bid] = $this->createJobWithBid($worker);
        $notification = NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $job->id,
                'job_title' => $job->title,
                'bid_id' => $bid->id,
            ],
            'read_at' => null,
        ]);

        $response = $this->withToken($this->getToken($otherWorker))
            ->patchJson('/api/v1/notifications/' . $notification->id . '/read');

        $response->assertStatus(403);
    }

    public function test_mark_notification_as_read_returns_not_found(): void
    {
        $worker = UserFactory::new()->create(['role' => 'worker']);

        $response = $this->withToken($this->getToken($worker))
            ->patchJson('/api/v1/notifications/' . Str::uuid() . '/read');

        $response->assertStatus(404);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $worker = UserFactory::new()->create(['role' => 'worker']);

        [$jobOne, $bidOne] = $this->createJobWithBid($worker);
        NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobOne->id,
                'job_title' => $jobOne->title,
                'bid_id' => $bidOne->id,
            ],
            'read_at' => null,
        ]);

        [$jobTwo, $bidTwo] = $this->createJobWithBid($worker);
        NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobTwo->id,
                'job_title' => $jobTwo->title,
                'bid_id' => $bidTwo->id,
            ],
            'read_at' => null,
        ]);

        [$jobRead, $bidRead] = $this->createJobWithBid($worker);
        NotificationFactory::new()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'data' => [
                'job_id' => $jobRead->id,
                'job_title' => $jobRead->title,
                'bid_id' => $bidRead->id,
            ],
            'read_at' => now(),
        ]);

        $response = $this->withToken($this->getToken($worker))
            ->patchJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $worker->id,
            'read_at' => null,
        ]);
    }

    public function test_mark_all_notifications_forbidden_for_client(): void
    {
        $client = UserFactory::new()->create(['role' => 'client']);

        $response = $this->withToken($this->getToken($client))
            ->patchJson('/api/v1/notifications/read-all');

        $response->assertStatus(403);
    }
}
