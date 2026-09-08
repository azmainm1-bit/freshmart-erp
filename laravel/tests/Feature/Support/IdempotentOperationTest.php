<?php

namespace Tests\Feature\Support;

use App\Exceptions\IdempotencyConflictException;
use App\Models\User;
use App\Support\Idempotency\IdempotentOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class IdempotentOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_call_executes_the_operation_and_is_not_marked_replayed(): void
    {
        $user = User::factory()->create();
        $calls = 0;

        $result = IdempotentOperation::run($user, 'test.op', 'key-1', ['a' => 1], function () use (&$calls) {
            $calls++;

            return ['status' => 201, 'body' => ['ok' => true]];
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($result['replayed']);
        $this->assertSame(201, $result['status']);
        $this->assertSame(['ok' => true], $result['body']);
    }

    public function test_retry_with_the_same_key_and_payload_replays_without_re_executing(): void
    {
        $user = User::factory()->create();
        $calls = 0;
        $operation = function () use (&$calls) {
            $calls++;

            return ['status' => 201, 'body' => ['count' => $calls]];
        };

        $first = IdempotentOperation::run($user, 'test.op', 'key-2', ['a' => 1], $operation);
        $second = IdempotentOperation::run($user, 'test.op', 'key-2', ['a' => 1], $operation);

        $this->assertSame(1, $calls, 'the operation must not run twice');
        $this->assertFalse($first['replayed']);
        $this->assertTrue($second['replayed']);
        $this->assertSame($first['body'], $second['body']);
        $this->assertSame(1, DB::table('idempotency_keys')->count(), 'exactly one row, not two');
    }

    public function test_same_key_with_a_different_payload_is_rejected_as_conflicting(): void
    {
        $user = User::factory()->create();
        $operation = fn () => ['status' => 201, 'body' => ['ok' => true]];

        IdempotentOperation::run($user, 'test.op', 'key-3', ['a' => 1], $operation);

        $this->expectException(IdempotencyConflictException::class);
        IdempotentOperation::run($user, 'test.op', 'key-3', ['a' => 2], $operation);
    }

    public function test_keys_are_scoped_per_user_not_global(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $calls = 0;
        $operation = function () use (&$calls) {
            $calls++;

            return ['status' => 201, 'body' => ['caller' => $calls]];
        };

        $resultA = IdempotentOperation::run($userA, 'test.op', 'shared-key', ['a' => 1], $operation);
        $resultB = IdempotentOperation::run($userB, 'test.op', 'shared-key', ['a' => 1], $operation);

        $this->assertSame(2, $calls, 'different users with the same client-generated key must not collide');
        $this->assertFalse($resultA['replayed']);
        $this->assertFalse($resultB['replayed']);
    }

    /**
     * Proves the crash-recovery property from docs/ARCHITECTURE.md: if the
     * business operation fails, NOTHING is left behind — no stuck claim,
     * no row requiring manual cleanup. A retry with the same key is free
     * to succeed normally afterwards. This is what makes deleting stale
     * claims unnecessary (the old Node app's design, explicitly not
     * ported — see docs/ARCHITECTURE.md).
     */
    public function test_a_failed_operation_leaves_no_trace_and_a_retry_can_succeed_cleanly(): void
    {
        $user = User::factory()->create();

        try {
            IdempotentOperation::run($user, 'test.op', 'key-4', ['a' => 1], function () {
                throw new RuntimeException('simulated business failure (e.g. insufficient stock)');
            });
            $this->fail('expected exception to propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated business failure (e.g. insufficient stock)', $e->getMessage());
        }

        $this->assertSame(0, DB::table('idempotency_keys')->count(), 'the failed claim must not persist');

        $calls = 0;
        $retry = IdempotentOperation::run($user, 'test.op', 'key-4', ['a' => 1], function () use (&$calls) {
            $calls++;

            return ['status' => 201, 'body' => ['ok' => true]];
        });

        $this->assertSame(1, $calls);
        $this->assertFalse($retry['replayed'], 'a retry after a genuine failure is a fresh attempt, not a replay');
    }
}
