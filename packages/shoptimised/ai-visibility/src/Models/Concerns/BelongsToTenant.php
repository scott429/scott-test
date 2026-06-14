<?php

namespace Shoptimised\AiVisibility\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Models\Scopes\TenantScope;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Applied to every retailer-owned model. Adds the global TenantScope and
 * auto-populates retailer_id from the active TenantContext on create.
 *
 * Do NOT apply to Retailer (the tenant root) or User (needed unscoped for auth).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->retailer_id)) {
                $retailerId = app(TenantContext::class)->retailerId();
                if ($retailerId !== null) {
                    $model->retailer_id = $retailerId;
                }
            }
        });
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }
}
