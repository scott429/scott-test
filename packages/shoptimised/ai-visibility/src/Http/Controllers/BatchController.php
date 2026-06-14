<?php

namespace Shoptimised\AiVisibility\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Shoptimised\AiVisibility\Http\Requests\CreateBatchRequest;
use Shoptimised\AiVisibility\Jobs\CancelVisibilityBatchJob;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Services\BatchService;

/**
 * Thin controller: validate, authorize, hand to BatchService, return. No slow
 * work happens in the request — the service only writes the batch row and
 * dispatches the first job. Returns JSON for now; the Phase 4 UI will consume it.
 */
class BatchController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected BatchService $batches) {}

    /** Distinct item groups available in a feed, for the selection table. */
    public function itemGroups(Feed $feed): JsonResponse
    {
        abort_unless($this->request()->user()->canAccessRetailer($feed->retailer_id), 403);

        $groups = Product::where('feed_id', $feed->id)
            ->whereNotNull('item_group_id')
            ->selectRaw('item_group_id, max(item_group_title) as item_group_title, max(brand) as brand, count(*) as variant_count')
            ->groupBy('item_group_id')
            ->orderBy('item_group_title')
            ->get();

        return response()->json(['data' => $groups]);
    }

    public function store(CreateBatchRequest $request): JsonResponse
    {
        $feed = Feed::findOrFail($request->integer('feed_id'));

        abort_unless($request->user()->canAccessRetailer($feed->retailer_id), 403);

        $batch = $this->batches->create($request->validated(), $request->user());

        return response()->json([
            'batch_id' => $batch->id,
            'status' => $batch->status->value,
            'total_item_groups' => $batch->total_item_groups,
        ], 201);
    }

    public function show(AiVisibilityBatch $batch): JsonResponse
    {
        $this->authorize('view', $batch);

        return response()->json([
            'batch_id' => $batch->id,
            'status' => $batch->status->value,
            'total_prompts' => $batch->total_prompts,
            'completed_prompts' => $batch->completed_prompts,
            'failed_prompts' => $batch->failed_prompts,
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
        ]);
    }

    public function cancel(AiVisibilityBatch $batch): JsonResponse
    {
        $this->authorize('cancel', $batch);

        CancelVisibilityBatchJob::dispatch($batch->id)
            ->onQueue(config('ai_visibility.queues.default'));

        return response()->json(['batch_id' => $batch->id, 'status' => 'cancelling']);
    }

    protected function request()
    {
        return request();
    }
}
