<?php

namespace Shoptimised\AiVisibility\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\FeedImporter;

/**
 * Centralised feed management: import a product feed from a URL and see each
 * feed's size at a glance, with a link into its data-reliability report.
 */
class FeedsPage extends Component
{
    use AuthorizesRequests;
    use UsesPackageLayout;

    public string $feedUrl = '';

    public string $feedName = '';

    public ?int $retailerId = null;

    public ?string $importedMessage = null;

    public function mount(): void
    {
        $this->authorize('create', AiVisibilityBatch::class);
        $this->retailerId = auth()->user()->retailer_id ?? Retailer::query()->value('id');
    }

    #[Computed]
    public function feeds()
    {
        return Feed::withCount('products')->orderByDesc('last_imported_at')->orderBy('name')->get();
    }

    #[Computed]
    public function retailers()
    {
        return auth()->user()->isShoptimisedStaff()
            ? Retailer::orderBy('name')->get(['id', 'name'])
            : Retailer::whereKey(auth()->user()->retailer_id)->get(['id', 'name']);
    }

    public function import(FeedImporter $importer): void
    {
        $this->authorize('create', AiVisibilityBatch::class);

        $this->validate([
            'feedUrl' => ['required', 'url'],
            'retailerId' => ['required', 'integer'],
            'feedName' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(auth()->user()->canAccessRetailer((int) $this->retailerId), 403);

        try {
            $response = Http::timeout(90)->retry(2, 1000, throw: false)->get($this->feedUrl);
            $response->throw();

            $summary = $importer->import((int) $this->retailerId, $response->body(), [
                'name' => $this->feedName !== '' ? $this->feedName : (parse_url($this->feedUrl, PHP_URL_HOST) ?: 'Imported feed'),
                'source_url' => $this->feedUrl,
                'country' => config('ai_visibility.defaults.country'),
            ]);
        } catch (\Throwable $e) {
            $this->addError('feedUrl', 'Import failed: '.$e->getMessage());

            return;
        }

        $this->importedMessage = sprintf(
            'Imported feed #%d — %d products, %d item groups, %d variant options, %d Q&A entries.',
            $summary['feed_id'], $summary['products'], $summary['item_groups'], $summary['variant_options'], $summary['qna_entries'],
        );

        $this->reset('feedUrl', 'feedName');
        unset($this->feeds);
    }

    public function render()
    {
        return view('ai-visibility::livewire.feeds')->layout($this->layoutName());
    }
}
