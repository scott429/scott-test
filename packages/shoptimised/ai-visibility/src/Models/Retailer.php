<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Database\Factories\RetailerFactory;

class Retailer extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): RetailerFactory
    {
        return RetailerFactory::new();
    }

    public function feeds(): HasMany
    {
        return $this->hasMany(Feed::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(config('ai_visibility.user_model'));
    }

    public function batches(): HasMany
    {
        return $this->hasMany(AiVisibilityBatch::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(FeedActionRecommendation::class);
    }
}
