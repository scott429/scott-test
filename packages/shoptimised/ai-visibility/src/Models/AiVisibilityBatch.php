<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class AiVisibilityBatch extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'platforms' => 'array',
            'selected_filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('ai_visibility.user_model'), 'created_by_user_id');
    }

    public function itemGroups(): HasMany
    {
        return $this->hasMany(AiVisibilityItemGroup::class, 'batch_id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(AiVisibilityPrompt::class, 'batch_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiVisibilityResult::class, 'batch_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(FeedActionRecommendation::class, 'batch_id');
    }
}
