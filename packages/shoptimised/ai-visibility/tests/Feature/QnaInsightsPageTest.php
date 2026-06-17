<?php

use Livewire\Livewire;
use Shoptimised\AiVisibility\Enums\PromptType;
use Shoptimised\AiVisibility\Livewire\QnaInsightsPage;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\AiVisibilityItemGroup;
use Shoptimised\AiVisibility\Models\AiVisibilityPrompt;
use Shoptimised\AiVisibility\Models\AiVisibilityResult;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Support\TenantContext;
use Shoptimised\AiVisibility\Tests\Stubs\User;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => app(TenantContext::class)->forget());

it('renders the buyer questions and flags AI-discovered FAQs', function () {
    $retailer = Retailer::factory()->create();
    $feed = Feed::factory()->for($retailer)->create();

    $batch = AiVisibilityBatch::create([
        'retailer_id' => $retailer->id, 'feed_id' => $feed->id, 'name' => 'Check',
        'status' => 'completed', 'platforms' => ['gemini'], 'total_item_groups' => 1, 'total_prompts' => 1,
    ]);
    $group = AiVisibilityItemGroup::create([
        'batch_id' => $batch->id, 'retailer_id' => $retailer->id, 'feed_id' => $feed->id,
        'item_group_id' => 'IG-EGG', 'item_group_title' => 'Egg chairs',
    ]);
    $prompt = AiVisibilityPrompt::create([
        'batch_id' => $batch->id, 'item_group_visibility_id' => $group->id, 'retailer_id' => $retailer->id,
        'prompt_text' => 'Are egg chairs weatherproof?', 'prompt_type' => PromptType::QnaLed->value,
        'source' => 'discovered_faq', 'country' => 'GB', 'language' => 'en', 'status' => 'completed', 'run_count' => 1,
    ]);
    AiVisibilityResult::create([
        'batch_id' => $batch->id, 'prompt_id' => $prompt->id, 'retailer_id' => $retailer->id,
        'platform' => 'gemini', 'surfaced' => false, 'competitor_count' => 1,
    ]);

    Permission::findOrCreate('view_reports');
    $user = User::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'secret', 'retailer_id' => $retailer->id]);
    $user->givePermissionTo('view_reports');

    Livewire::actingAs($user)
        ->test(QnaInsightsPage::class)
        ->assertSee('Are egg chairs weatherproof?')
        ->assertSee('AI-discovered');
});
