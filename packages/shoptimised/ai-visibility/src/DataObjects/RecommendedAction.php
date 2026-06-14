<?php

namespace Shoptimised\AiVisibility\DataObjects;

use Shoptimised\AiVisibility\Enums\ActionType;

final readonly class RecommendedAction
{
    public function __construct(
        public ActionType $actionType,
        public string $priority = 'medium',
        public ?string $reason = null,
        public ?string $evidenceSummary = null,
        public ?int $productId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'action_type' => $this->actionType->value,
            'priority' => $this->priority,
            'reason' => $this->reason,
            'evidence_summary' => $this->evidenceSummary,
            'product_id' => $this->productId,
        ];
    }
}
