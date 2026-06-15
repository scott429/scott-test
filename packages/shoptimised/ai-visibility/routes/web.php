<?php

use Illuminate\Support\Facades\Route;
use Shoptimised\AiVisibility\Http\Controllers\BatchController;
use Shoptimised\AiVisibility\Livewire\BatchProgressPage;
use Shoptimised\AiVisibility\Livewire\BatchResultsPage;
use Shoptimised\AiVisibility\Livewire\ItemGroupDetailPage;
use Shoptimised\AiVisibility\Livewire\LandingPage;
use Shoptimised\AiVisibility\Livewire\NewCheckPage;
use Shoptimised\AiVisibility\Livewire\QnaInsightsPage;
use Shoptimised\AiVisibility\Livewire\RecommendationsPage;

// Mounted by AiVisibilityServiceProvider using config('ai_visibility.routing').

// Reporting pages (full-page Livewire components).
Route::get('/', LandingPage::class)->name('aiv.landing');
Route::get('new', NewCheckPage::class)->name('aiv.new');
Route::get('batches/{batch}/progress', BatchProgressPage::class)->name('aiv.batches.progress');
Route::get('batches/{batch}/results', BatchResultsPage::class)->name('aiv.batches.results');
Route::get('groups/{itemGroup}', ItemGroupDetailPage::class)->name('aiv.groups.show');
Route::get('recommendations', RecommendationsPage::class)->name('aiv.recommendations');
Route::get('qna-insights', QnaInsightsPage::class)->name('aiv.qna');

// JSON endpoints (used by the new-check page helpers / external callers).
Route::get('feeds/{feed}/item-groups', [BatchController::class, 'itemGroups'])->name('aiv.feeds.item-groups');
Route::post('batches', [BatchController::class, 'store'])->name('aiv.batches.store');
Route::get('batches/{batch}', [BatchController::class, 'show'])->name('aiv.batches.show');
Route::post('batches/{batch}/cancel', [BatchController::class, 'cancel'])->name('aiv.batches.cancel');
