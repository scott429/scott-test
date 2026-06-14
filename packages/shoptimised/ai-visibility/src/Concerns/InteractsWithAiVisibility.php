<?php

namespace Shoptimised\AiVisibility\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Shoptimised\AiVisibility\Enums\Role;
use Shoptimised\AiVisibility\Models\Retailer;

/**
 * Add to the host application's User model alongside Spatie's HasRoles:
 *
 *   use Spatie\Permission\Traits\HasRoles;
 *   use Shoptimised\AiVisibility\Concerns\InteractsWithAiVisibility;
 *
 * Assumes the users table has a nullable retailer_id (added by this package's
 * migration). retailer_id = null means the user is Shoptimised staff.
 */
trait InteractsWithAiVisibility
{
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    /** Retailers a Shoptimised analyst is assigned to. */
    public function assignedRetailers(): BelongsToMany
    {
        return $this->belongsToMany(Retailer::class, 'analyst_assignments');
    }

    public function isShoptimisedStaff(): bool
    {
        return $this->retailer_id === null;
    }

    public function canAccessRetailer(int $retailerId): bool
    {
        if ($this->hasRole(Role::ShoptimisedAdmin->value)) {
            return true;
        }

        if ($this->hasRole(Role::ShoptimisedAnalyst->value)) {
            return $this->assignedRetailers()->whereKey($retailerId)->exists();
        }

        return $this->retailer_id === $retailerId;
    }
}
