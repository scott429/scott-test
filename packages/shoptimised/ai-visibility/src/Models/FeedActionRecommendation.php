<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shoptimised\AiVisibility\Enums\ActionType;
use Shoptimised\AiVisibility\Enums\RecommendationStatus;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class FeedActionRecommendation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action_type' => ActionType::class,
            'status' => RecommendationStatus::class,
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityBatch::class, 'batch_id');
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityItemGroup::class, 'item_group_visibility_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(config('ai_visibility.user_model'), 'assigned_to_user_id');
    }
}
