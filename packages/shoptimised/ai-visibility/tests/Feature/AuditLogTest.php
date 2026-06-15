<?php

use Illuminate\Support\Facades\Queue;
use Shoptimised\AiVisibility\Models\AuditLog;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\BatchService;
use Shoptimised\AiVisibility\Support\TenantContext;
use Shoptimised\AiVisibility\Tests\Stubs\User;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

it('records an audit entry when a batch is created', function () {
    Queue::fake();

    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();
    $user = User::create(['name' => 'Analyst', 'email' => 'a@example.com', 'password' => 'secret']);

    app(BatchService::class)->create([
        'feed_id' => $feed->id,
        'platforms' => ['manual'],
        'item_group_ids' => ['IG-1', 'IG-2'],
        'runs_per_prompt' => 1,
        'prompts_per_item_group' => 3,
    ], $user);

    $audit = AuditLog::where('action', 'batch.created')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->retailer_id)->toBe($retailer->id)
        ->and(data_get($audit->metadata, 'item_groups'))->toBe(2)
        ->and(data_get($audit->metadata, 'platforms'))->toBe(['manual']);
});

it('records an audit entry for a subject with metadata and infers the retailer', function () {
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    $audit = AuditLog::record('batch.cancelled', $feed, ['note' => 'manual cancel']);

    expect($audit->action)->toBe('batch.cancelled')
        ->and($audit->auditable_id)->toBe($feed->id)
        ->and($audit->retailer_id)->toBe($retailer->id)
        ->and(data_get($audit->metadata, 'note'))->toBe('manual cancel');
});
