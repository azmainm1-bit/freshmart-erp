<?php

namespace Tests\Feature\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Deliberately does NOT use RefreshDatabase: that trait wraps each test in
 * a transaction on Laravel's own connection, which the two independent raw
 * PDO connections below (simulating two separate HTTP requests / PHP-FPM
 * workers) would never see — Postgres never shows one session's
 * uncommitted work to another. This test manages its own committed rows
 * and cleans them up explicitly in tearDown().
 */
class IdempotentOperationConcurrencyTest extends TestCase
{
    private ?string $userId = null;

    protected function tearDown(): void
    {
        if ($this->userId) {
            DB::table('idempotency_keys')->where('user_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
        }

        parent::tearDown();
    }

    /**
     * Proves the underlying Postgres mechanism the whole idempotency
     * design depends on (docs/ARCHITECTURE.md): two sessions attempting to
     * insert the same (user_id, endpoint, key) row serialize on the
     * unique index — the second session blocks until the first resolves,
     * rather than both succeeding. This is what makes concurrent
     * duplicate submissions safe without any application-level locking.
     */
    public function test_concurrent_claims_for_the_same_key_serialize_on_the_unique_index(): void
    {
        $user = User::factory()->create();
        $this->userId = $user->id;

        $config = config('database.connections.pgsql');
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']);

        $connA = new PDO($dsn, $config['username'], $config['password']);
        $connB = new PDO($dsn, $config['username'], $config['password']);

        $insert = 'insert into idempotency_keys (id, user_id, endpoint, "key", fingerprint, response_status, response_body, created_at)
                   values (gen_random_uuid(), ?, \'race.op\', \'race-key\', \'fingerprint-a\', 0, \'{}\', now())';

        $connA->beginTransaction();
        $connA->prepare($insert)->execute([$user->id]);
        // connA has NOT committed — its row is not yet resolved/visible to anyone else.

        $connB->exec("SET statement_timeout = '600'");
        $connB->beginTransaction();
        $blocked = false;
        $start = microtime(true);
        try {
            $connB->prepare($insert)->execute([$user->id]);
        } catch (\PDOException $e) {
            $blocked = true; // statement_timeout (57014): connB was genuinely waiting on connA's uncommitted row
        }
        $elapsed = microtime(true) - $start;
        $connB->rollBack();

        $this->assertTrue($blocked, 'a second concurrent insert of the same key must block, not succeed immediately');
        $this->assertGreaterThan(0.5, $elapsed, 'the block must last roughly the full statement_timeout, proving it was really waiting, not failing instantly');

        $connA->commit();
        $this->assertSame(1, DB::table('idempotency_keys')->where('key', 'race-key')->count());
    }
}
