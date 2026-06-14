<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Enums\PromptType;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class AiVisibilityPrompt extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prompt_type' => PromptType::class,
            'run_count' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityBatch::class, 'batch_id');
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityItemGroup::class, 'item_group_visibility_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiVisibilityResult::class, 'prompt_id');
    }
}
