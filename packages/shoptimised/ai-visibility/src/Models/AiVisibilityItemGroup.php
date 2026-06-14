<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class AiVisibilityItemGroup extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'top_competitors' => 'array',
            'recommended_actions' => 'array',
            'total_revenue' => 'decimal:2',
            'ai_visibility_score' => 'decimal:2',
            'surfaced_rate' => 'decimal:2',
            'average_position' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityBatch::class, 'batch_id');
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function representativeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'representative_product_id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(AiVisibilityPrompt::class, 'item_group_visibility_id');
    }
}
