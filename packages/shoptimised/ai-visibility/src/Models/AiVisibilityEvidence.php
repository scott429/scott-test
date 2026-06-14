<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shoptimised\AiVisibility\Enums\EvidenceType;

class AiVisibilityEvidence extends Model
{
    use HasFactory;

    protected $table = 'ai_visibility_evidence';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_type' => EvidenceType::class,
            'metadata' => 'array',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityResult::class, 'result_id');
    }
}
