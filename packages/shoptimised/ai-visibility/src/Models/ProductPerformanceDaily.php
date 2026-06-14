<?php

namespace Shoptimised\AiVisibility\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shoptimised\AiVisibility\Database\Factories\ProductPerformanceDailyFactory;
use Shoptimised\AiVisibility\Models\Concerns\BelongsToTenant;

class ProductPerformanceDaily extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'product_performance_daily';

    protected $guarded = [];

    protected static function newFactory(): ProductPerformanceDailyFactory
    {
        return ProductPerformanceDailyFactory::new();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cost' => 'decimal:2',
            'conversions' => 'decimal:2',
            'revenue' => 'decimal:2',
            'ctr' => 'decimal:4',
            'cpc' => 'decimal:4',
            'conversion_rate' => 'decimal:4',
            'roas' => 'decimal:4',
        ];
    }
}
