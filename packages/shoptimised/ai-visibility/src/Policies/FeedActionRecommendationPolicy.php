<?php

namespace Shoptimised\AiVisibility\Policies;

use App\Models\User;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;

class FeedActionRecommendationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_reports');
    }

    public function view(User $user, FeedActionRecommendation $recommendation): bool
    {
        return $user->can('view_reports') && $user->canAccessRetailer($recommendation->retailer_id);
    }

    /**
     * Approving / completing / rejecting a recommendation is restricted to
     * Shoptimised admin and analyst (per the permissions matrix).
     */
    public function changeStatus(User $user, FeedActionRecommendation $recommendation): bool
    {
        return $user->can('approve_recommendations') && $user->canAccessRetailer($recommendation->retailer_id);
    }

    public function update(User $user, FeedActionRecommendation $recommendation): bool
    {
        return $this->changeStatus($user, $recommendation);
    }
}
