<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Model;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Append-only audit trail for sensitive actions (batch create/cancel,
 * recommendation status changes). Deliberately NOT tenant-scoped so records are
 * always written regardless of the active tenant; retailer_id is stored for
 * filtering and staff oversight.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_visibility_audits';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /**
     * Record an audit entry. Actor is the authenticated user (null in queue/CLI
     * context); retailer is taken from the subject or the active tenant.
     *
     * @param  array<string,mixed>  $metadata
     */
    public static function record(string $action, ?Model $subject = null, array $metadata = [], ?int $retailerId = null): self
    {
        return static::create([
            'retailer_id' => $retailerId ?? $subject?->retailer_id ?? app(TenantContext::class)->retailerId(),
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }
}
