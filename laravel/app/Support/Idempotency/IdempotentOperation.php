<?php

namespace App\Support\Idempotency;

use App\Exceptions\IdempotencyConflictException;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crash-safe, database-only idempotency. See docs/ARCHITECTURE.md for the
 * full design rationale — summary:
 *
 *   - The claim row and the business write it guards commit or roll back
 *     together (same outer transaction; the claim insert uses a SAVEPOINT
 *     so a conflicting-key error can be caught without poisoning the
 *     outer transaction). There is therefore no reachable "pending"
 *     state and nothing is ever deleted to recover from one.
 *   - Concurrent requests with the same key serialize on Postgres's own
 *     unique-index insert blocking: the second waits for the first to
 *     resolve, then either replays a completed result or claims the slot
 *     itself (if the first rolled back).
 *   - Keys are scoped to (user_id, endpoint, key) — never global.
 */
class IdempotentOperation
{
    /**
     * @param  array<string, mixed>  $payload  the request data the fingerprint is computed from
     * @param  Closure(): array{status: int, body: array}  $callback  the business operation; must return
     *                                                                a [status, body] pair, and should
     *                                                                throw on failure (which rolls back
     *                                                                both the business writes and the
     *                                                                claim together — no cleanup needed)
     * @return array{status: int, body: array, replayed: bool}
     */
    public static function run(User $user, string $endpoint, string $key, array $payload, Closure $callback): array
    {
        $fingerprint = self::fingerprint($payload);

        return DB::transaction(function () use ($user, $endpoint, $key, $fingerprint, $callback) {
            $id = (string) Str::uuid();

            try {
                DB::transaction(function () use ($id, $user, $endpoint, $key, $fingerprint) {
                    DB::table('idempotency_keys')->insert([
                        'id' => $id,
                        'user_id' => $user->id,
                        'endpoint' => $endpoint,
                        'key' => $key,
                        'fingerprint' => $fingerprint,
                        'response_status' => 0,
                        'response_body' => json_encode(new \stdClass),
                        'created_at' => now(),
                    ]);
                });
            } catch (QueryException $e) {
                if (! self::isUniqueViolation($e)) {
                    throw $e;
                }

                $existing = DB::table('idempotency_keys')
                    ->where('user_id', $user->id)
                    ->where('endpoint', $endpoint)
                    ->where('key', $key)
                    ->first();

                if (! $existing) {
                    // Should not happen: a unique violation implies a committed
                    // conflicting row exists and is now visible under MVCC.
                    throw new RuntimeException('Idempotency key conflict detected but no existing row found', previous: $e);
                }

                if ($existing->fingerprint !== $fingerprint) {
                    throw new IdempotencyConflictException(
                        'This Idempotency-Key was already used with a different request body'
                    );
                }

                return [
                    'status' => $existing->response_status,
                    'body' => json_decode((string) $existing->response_body, true),
                    'replayed' => true,
                ];
            }

            $result = $callback();

            DB::table('idempotency_keys')->where('id', $id)->update([
                'response_status' => $result['status'],
                'response_body' => json_encode($result['body']),
            ]);

            return ['status' => $result['status'], 'body' => $result['body'], 'replayed' => false];
        });
    }

    private static function fingerprint(array $payload): string
    {
        self::recursiveKsort($payload);

        return hash('sha256', json_encode($payload));
    }

    private static function recursiveKsort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursiveKsort($value);
            }
        }
    }

    private static function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return $sqlState === '23505';
    }
}
