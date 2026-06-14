<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shoptimised\AiVisibility\Database\Factories\ProductFactory;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected function casts(): array
    {
        return [
            'custom_labels' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function conversationalAttributes(): HasMany
    {
        return $this->hasMany(ProductConversationalAttribute::class);
    }

    public function performance(): HasMany
    {
        return $this->hasMany(ProductPerformanceDaily::class, 'product_id_external', 'product_id_external');
    }
}
