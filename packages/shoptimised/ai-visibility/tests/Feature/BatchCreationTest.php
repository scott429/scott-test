<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Shoptimised\AiVisibility\Enums\BatchStatus;
use Shoptimised\AiVisibility\Jobs\CreateVisibilityBatchJob;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\BatchService;
use Shoptimised\AiVisibility\Support\TenantContext;
use Shoptimised\AiVisibility\Tests\Stubs\User;

beforeEach(fn () => app(TenantContext::class)->forget());

it('creates a queued batch and dispatches the first job', function () {
    Queue::fake();

    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();
    $user = User::create([
        'name' => 'Analyst',
        'email' => 'analyst@example.test',
        'password' => 'secret-password',
        'retailer_id' => null,
    ]);

    $batch = app(BatchService::class)->create([
        'feed_id' => $feed->id,
        'name' => 'June visibility check',
        'platforms' => ['manual', 'openai'],
        'item_group_ids' => ['IG-RATTAN', 'IG-PARASOL'],
        'runs_per_prompt' => 2,
        'prompts_per_item_group' => 8,
    ], $user);

    expect($batch->status)->toBe(BatchStatus::Queued)
        ->and($batch->total_item_groups)->toBe(2)
        ->and($batch->retailer_id)->toBe($retailer->id)
        ->and($batch->created_by_user_id)->toBe($user->id);

    $this->assertDatabaseHas('ai_visibility_batches', [
        'id' => $batch->id,
        'status' => BatchStatus::Queued->value,
        'feed_id' => $feed->id,
    ]);

    Queue::assertPushed(CreateVisibilityBatchJob::class, fn ($job) => $job->batchId === $batch->id);
});

it('rejects a batch that exceeds the item-group limit', function () {
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();
    $user = User::create([
        'name' => 'Analyst',
        'email' => 'analyst2@example.test',
        'password' => 'secret-password',
    ]);

    $tooMany = array_map(fn ($i) => "IG-{$i}", range(1, 26)); // max is 25

    app(BatchService::class)->create([
        'feed_id' => $feed->id,
        'platforms' => ['manual'],
        'item_group_ids' => $tooMany,
    ], $user);
})->throws(ValidationException::class);
