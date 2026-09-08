<?php

namespace App\Support;

use App\Models\AuditLogEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin wrapper around AuditLogEntry — always call this from inside the same
 * database transaction as the business write it's documenting, so the
 * audit trail and the effect it describes commit or roll back together.
 */
class AuditLog
{
    public static function record(User $actor, string $action, ?Model $subject = null, array $metadata = []): AuditLogEntry
    {
        return AuditLogEntry::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
