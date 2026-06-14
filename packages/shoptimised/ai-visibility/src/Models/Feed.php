<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Database\Factories\FeedFactory;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class Feed extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): FeedFactory
    {
        return FeedFactory::new();
    }

    protected function casts(): array
    {
        return ['last_imported_at' => 'datetime'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(AiVisibilityBatch::class);
    }
}
