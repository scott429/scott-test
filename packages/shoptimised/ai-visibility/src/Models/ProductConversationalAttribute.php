<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shoptimised\AiVisibility\Database\Factories\ProductConversationalAttributeFactory;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class ProductConversationalAttribute extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): ProductConversationalAttributeFactory
    {
        return ProductConversationalAttributeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'attribute_type' => AttributeType::class,
            'attribute_value' => 'array',
            'live_in_feed' => 'boolean',
            'confidence_score' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
