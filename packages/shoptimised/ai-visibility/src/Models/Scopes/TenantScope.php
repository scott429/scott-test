<?php

namespace Shoptimised\AiVisibility\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Shoptimised\AiVisibility\Support\TenantContext;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $retailerId = app(TenantContext::class)->retailerId();

        if ($retailerId !== null) {
            $builder->where($model->getTable().'.retailer_id', $retailerId);
        }
    }
}
