<?php

use Livewire\Livewire;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Livewire\BatchResultsPage;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityCompetitor;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\AuditLog;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Support\TenantContext;
use Shoptimised\AiVisibility\Tests\Stubs\User;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => app(TenantContext::class)->forget());

/**
 * @return array{batch: AiVisibilityBatch, rec: FeedActionRecommendation, user: User}
 */
function batchResultsModalFixture(bool $canManage = true): array
{
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    Product::factory()->create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs',
    ]);

    $batch = AiVisibilityBatch::create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id, 'name' => 'Check',
        'status' => 'completed', 'platforms' => ['gemini'], 'total_item_groups' => 1, 'total_prompts' => 1,
    ]);

    $itemGroup = AiVisibilityItemGroup::create([
        'batch_id' => $batch->id, 'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs',
    ]);

    $prompt = AiVisibilityPrompt::create([
        'batch_id' => $batch->id, 'item_group_visibility_id' => $itemGroup->id, 'retailer_id' => $retailer->id,
        'prompt_text' => 'Do egg chairs come with a stand?', 'prompt_type' => 'qna_led',
        'country' => 'GB', 'language' => 'en', 'status' => 'completed', 'run_count' => 1,
    ]);

    $result = AiVisibilityResult::create([
        'batch_id' => $batch->id, 'prompt_id' => $prompt->id, 'retailer_id' => $retailer->id,
        'platform' => 'gemini', 'surfaced' => false, 'competitor_count' => 1,
    ]);
    AiVisibilityCompetitor::create([
        'result_id' => $result->id, 'retailer_id' => $retailer->id, 'competitor_domain' => 'dunelm.com',
    ]);

    $rec = FeedActionRecommendation::create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id, 'batch_id' => $batch->id,
        'item_group_visibility_id' => $itemGroup->id, 'action_type' => 'add_qna',
        'priority' => 'high', 'status' => 'suggested', 'reason' => 'Competitors answered; you did not.',
    ]);

    Permission::findOrCreate('view_reports');
    Permission::findOrCreate('approve_recommendations');

    $user = User::create([
        'name' => 'Analyst', 'email' => 'analyst@example.test',
        'password' => 'secret', 'retailer_id' => $retailer->id,
    ]);
    $user->givePermissionTo($canManage ? ['view_reports', 'approve_recommendations'] : ['view_reports']);

    return ['batch' => $batch, 'rec' => $rec, 'user' => $user];
}

it('opens the recommendation modal with the competitor-only buyer questions', function () {
    ['batch' => $batch, 'rec' => $rec, 'user' => $user] = batchResultsModalFixture();

    Livewire::actingAs($user)
        ->test(BatchResultsPage::class, ['batch' => $batch])
        ->call('viewDetail', $rec->id)
        ->assertSet('detailId', $rec->id)
        ->assertSee('Do egg chairs come with a stand?')
        ->assertSee('dunelm.com')
        ->assertSee('Add 1 to feed Q&A', escape: false);
});

it('pushes the gap questions into the feed Q&A from the results page', function () {
    ['batch' => $batch, 'rec' => $rec, 'user' => $user] = batchResultsModalFixture();

    Livewire::actingAs($user)
        ->test(BatchResultsPage::class, ['batch' => $batch])
        ->call('viewDetail', $rec->id)
        ->call('pushQnaToFeed', $rec->id)
        ->assertSee('Added 1 question(s) to the Q&A of 1 product(s)');

    $product = Product::where('item_group_id', 'IG-EGG')->first();
    $attr = ProductConversationalAttribute::where('product_id', $product->id)
        ->where('attribute_type', AttributeType::QuestionAndAnswer->value)
        ->first();

    expect($attr)->not->toBeNull()
        ->and($attr->live_in_feed)->toBeTrue()
        ->and(data_get($attr->attribute_value, 'items.0.question'))->toBe('Do egg chairs come with a stand?');

    expect($rec->fresh()->status->value)->toBe('in_progress');
    expect(AuditLog::where('action', 'recommendation.qna_pushed_to_feed')->exists())->toBeTrue();
});

it('hides the push action from users without approve permission', function () {
    ['batch' => $batch, 'rec' => $rec, 'user' => $user] = batchResultsModalFixture(canManage: false);

    Livewire::actingAs($user)
        ->test(BatchResultsPage::class, ['batch' => $batch])
        ->call('viewDetail', $rec->id)
        ->assertSee('Do egg chairs come with a stand?')
        ->assertDontSee('Add 1 to feed Q&A', escape: false);
});
