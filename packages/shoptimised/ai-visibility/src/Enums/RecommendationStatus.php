<?php

namespace Shoptimised\AiVisibility\Enums;

enum RecommendationStatus: string
{
    case Suggested = 'suggested';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
