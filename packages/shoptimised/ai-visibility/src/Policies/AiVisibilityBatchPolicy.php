<?php

namespace Shoptimised\AiVisibility\Policies;

use App\Models\User;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;

class AiVisibilityBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_reports');
    }

    public function view(User $user, AiVisibilityBatch $batch): bool
    {
        return $user->can('view_reports') && $user->canAccessRetailer($batch->retailer_id);
    }

    /**
     * Base create ability. The controller also calls createForRetailer() to
     * confirm the user may create against the chosen retailer.
     */
    public function create(User $user): bool
    {
        return $user->can('create_batches');
    }

    public function createForRetailer(User $user, int $retailerId): bool
    {
        return $user->can('create_batches') && $user->canAccessRetailer($retailerId);
    }

    public function update(User $user, AiVisibilityBatch $batch): bool
    {
        return $user->can('manage_batches') && $user->canAccessRetailer($batch->retailer_id);
    }

    public function cancel(User $user, AiVisibilityBatch $batch): bool
    {
        return $this->update($user, $batch);
    }

    public function delete(User $user, AiVisibilityBatch $batch): bool
    {
        return $this->update($user, $batch);
    }
}
