<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Enums\MatchType;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class AiVisibilityResult extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'surfaced' => 'boolean',
            'match_type' => MatchType::class,
            'confidence_score' => 'integer',
            'mention_position' => 'integer',
            'citation_position' => 'integer',
            'competitors_surfaced' => 'array',
            'competitor_count' => 'integer',
            'qna_theme_gaps' => 'array',
            'variant_gaps' => 'array',
            'related_product_gaps' => 'array',
            'document_gaps' => 'array',
            'recommended_actions' => 'array',
            'tested_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityBatch::class, 'batch_id');
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityPrompt::class, 'prompt_id');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(AiVisibilityCompetitor::class, 'result_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AiVisibilityEvidence::class, 'result_id');
    }
}
