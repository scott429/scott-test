<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class AiVisibilityCompetitor extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mention_position' => 'integer',
            'citation_position' => 'integer',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AiVisibilityResult::class, 'result_id');
    }
}
