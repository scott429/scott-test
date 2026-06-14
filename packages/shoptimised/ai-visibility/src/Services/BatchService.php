<?php

namespace Shoptimised\AiVisibility\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Jobs\CreateVisibilityBatchJob;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\Feed;

/**
 * Creates a visibility batch from a validated request and queues processing.
 * The web request does nothing slow here — it only writes the batch row and
 * dispatches the first job.
 */
class BatchService
{
    public function __construct(protected PromptGenerator $promptGenerator) {}

    /**
     * @param  array{
     *     feed_id:int, name?:string, platforms:array<int,string>,
     *     item_group_ids:array<int,string>, runs_per_prompt?:int,
     *     prompts_per_item_group?:int, selected_filters?:array,
     *     country?:string, language?:string
     * }  $data
     */
    public function create(array $data, Authenticatable $user): AiVisibilityBatch
    {
        $feed = Feed::findOrFail($data['feed_id']);

        $itemGroupIds = array_values(array_unique($data['item_group_ids']));
        $runsPerPrompt = (int) ($data['runs_per_prompt'] ?? config('ai_visibility.limits.max_runs_per_prompt'));
        $promptsPerGroup = (int) ($data['prompts_per_item_group'] ?? config('ai_visibility.limits.default_prompts_per_item_group'));
        $platforms = array_values($data['platforms']);

        $this->assertWithinLimits($itemGroupIds, $platforms, $promptsPerGroup, $runsPerPrompt);

        $batch = new AiVisibilityBatch([
            'feed_id' => $feed->id,
            'retailer_id' => $feed->retailer_id,
            'created_by_user_id' => $user->getAuthIdentifier(),
            'name' => $data['name'] ?? $this->defaultName(),
            'status' => BatchStatus::Queued,
            'platforms' => $platforms,
            'selected_filters' => [
                'item_group_ids' => $itemGroupIds,
                'runs_per_prompt' => $runsPerPrompt,
                'prompts_per_item_group' => $promptsPerGroup,
                'country' => $data['country'] ?? config('ai_visibility.defaults.country'),
                'language' => $data['language'] ?? config('ai_visibility.defaults.language'),
                'filters' => $data['selected_filters'] ?? [],
            ],
            'total_item_groups' => count($itemGroupIds),
            'total_prompts' => 0,
        ]);
        $batch->retailer_id = $feed->retailer_id;
        $batch->save();

        CreateVisibilityBatchJob::dispatch($batch->id)
            ->onQueue(config('ai_visibility.queues.default'));

        return $batch;
    }

    public function estimateRuns(int $itemGroups, int $platforms, int $promptsPerGroup, int $runsPerPrompt): int
    {
        return $itemGroups * $promptsPerGroup * $platforms * $runsPerPrompt;
    }

    protected function assertWithinLimits(array $itemGroupIds, array $platforms, int $promptsPerGroup, int $runsPerPrompt): void
    {
        $limits = config('ai_visibility.limits');

        if (count($itemGroupIds) === 0) {
            throw ValidationException::withMessages(['item_group_ids' => 'Select at least one item group.']);
        }
        if (count($itemGroupIds) > $limits['max_item_groups_per_batch']) {
            throw ValidationException::withMessages(['item_group_ids' => "Too many item groups (max {$limits['max_item_groups_per_batch']})."]);
        }
        if ($runsPerPrompt > $limits['max_runs_per_prompt']) {
            throw ValidationException::withMessages(['runs_per_prompt' => "Too many runs per prompt (max {$limits['max_runs_per_prompt']})."]);
        }
        $estimatedPrompts = count($itemGroupIds) * $promptsPerGroup;
        if ($estimatedPrompts > $limits['max_prompts_per_batch']) {
            throw ValidationException::withMessages(['item_group_ids' => "Estimated prompts ({$estimatedPrompts}) exceed the batch limit ({$limits['max_prompts_per_batch']})."]);
        }
        if (count($platforms) === 0) {
            throw ValidationException::withMessages(['platforms' => 'Select at least one platform.']);
        }
    }

    protected function defaultName(): string
    {
        return now()->format('F Y').' visibility check';
    }
}
