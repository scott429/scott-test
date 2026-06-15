<?php

namespace Shoptimised\AiVisibility\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\Models\Retailer;
use Shoptimised\AiVisibility\Services\FeedImporter;

/**
 * Imports a Google Shopping product feed (XML or TSV) for a retailer.
 *
 *   php artisan ai-visibility:import-feed --retailer=1 --url=https://example.com/products.xml
 *   php artisan ai-visibility:import-feed --retailer=1 --file=storage/feed.tsv --name="Outdoor feed"
 */
class ImportFeedCommand extends Command
{
    protected $signature = 'ai-visibility:import-feed
        {--retailer= : Retailer (tenant) id to import into}
        {--url= : URL of the Google Shopping feed (XML or TSV)}
        {--file= : Local path to a feed file instead of a URL}
        {--name= : Feed name (defaults to the source host or "Imported feed")}
        {--merchant-center-id= : Optional Google Merchant Center account id}
        {--country= : 2-letter country code, e.g. GB}
        {--currency= : 3-letter currency code, e.g. GBP}
        {--format=auto : auto|xml|tsv}';

    protected $description = 'Import a Google Shopping product feed (XML/TSV) into the AI Visibility tables.';

    public function handle(FeedImporter $importer): int
    {
        $retailerId = (int) $this->option('retailer');
        if ($retailerId <= 0 || ! Retailer::whereKey($retailerId)->exists()) {
            $this->error('Provide a valid --retailer=<id>. Known retailers:');
            Retailer::query()->get(['id', 'name'])->each(fn ($r) => $this->line("  {$r->id}  {$r->name}"));

            return self::FAILURE;
        }

        $url = $this->option('url');
        $file = $this->option('file');

        if (! $url && ! $file) {
            $this->error('Provide either --url=<feed-url> or --file=<path>.');

            return self::FAILURE;
        }

        try {
            $content = $this->loadContent($url, $file);
        } catch (\Throwable $e) {
            $this->error('Could not read the feed: '.$e->getMessage());

            return self::FAILURE;
        }

        $name = $this->option('name') ?: ($url ? parse_url($url, PHP_URL_HOST) : basename((string) $file)) ?: 'Imported feed';

        $this->info("Importing feed \"{$name}\" for retailer {$retailerId} …");

        try {
            $summary = $importer->import($retailerId, $content, [
                'name' => $name,
                'source_url' => $url ?: null,
                'merchant_center_id' => $this->option('merchant-center-id'),
                'country' => $this->option('country'),
                'currency' => $this->option('currency'),
                'format' => $this->option('format'),
            ]);
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Done. Feed #%d — %d products across %d item groups (%d variant options).',
            $summary['feed_id'], $summary['products'], $summary['item_groups'], $summary['variant_options'],
        ));
        $this->line('Start a check at the "New visibility check" page, then run: php artisan queue:work --queue=ai-visibility,parsing,default');

        return self::SUCCESS;
    }

    protected function loadContent(?string $url, ?string $file): string
    {
        if ($url) {
            $response = Http::timeout(60)->retry(2, 500)->get($url);
            $response->throw();

            return $response->body();
        }

        if (! is_file((string) $file)) {
            throw new \RuntimeException("File not found: {$file}");
        }

        return (string) file_get_contents($file);
    }
}
