<?php

namespace Shoptimised\AiVisibility\Services;

use Illuminate\Support\Str;
use Shoptimised\AiVisibility\Enums\AttributeType;
use Shoptimised\AiVisibility\Models\Feed;
use Shoptimised\AiVisibility\Models\Product;
use Shoptimised\AiVisibility\Models\ProductConversationalAttribute;
use Shoptimised\AiVisibility\Support\TenantContext;

/**
 * Imports a Google Shopping (Merchant Center) product feed into the package's
 * feeds/products tables, deriving item groups, variant options and the
 * conversational attributes the prompt generator reads.
 *
 * Supports the two formats Merchant Center exports: XML (RSS 2.0 with the
 * `g:` namespace) and tab-separated values (TSV) with a header row.
 *
 * Re-running against the same feed is idempotent: products are matched on
 * (feed_id, product_id_external) and updated in place.
 *
 * @phpstan-type ImportSummary array{feed_id:int,products:int,item_groups:int,variant_options:int}
 */
class FeedImporter
{
    public function __construct(protected TenantContext $tenant) {}

    /**
     * @param  array{name?:string,source_url?:string,merchant_center_id?:string,country?:string,currency?:string,format?:string}  $options
     * @return ImportSummary
     */
    public function import(int $retailerId, string $content, array $options = []): array
    {
        $items = $this->parse($content, $options['format'] ?? 'auto');

        if ($items === []) {
            throw new \RuntimeException('No products found in the feed. Check the URL/file and format.');
        }

        // Derive a clean item-group title (longest common prefix of the group's
        // titles) since Google feeds have no canonical group-title field.
        $titlesByGroup = [];
        foreach ($items as $item) {
            $groupId = $item['item_group_id'] !== '' ? $item['item_group_id'] : $item['id'];
            $titlesByGroup[$groupId][] = $item['title'];
        }
        $groupTitles = [];
        foreach ($titlesByGroup as $groupId => $titles) {
            $groupTitles[$groupId] = $this->groupTitle($titles);
        }

        return $this->tenant->runAs($retailerId, function () use ($retailerId, $items, $groupTitles, $options) {
            $feed = Feed::updateOrCreate(
                [
                    'retailer_id' => $retailerId,
                    'name' => $options['name'] ?? 'Imported feed',
                ],
                [
                    'merchant_center_id' => $options['merchant_center_id'] ?? null,
                    'country' => $options['country'] ?? null,
                    'currency' => $options['currency'] ?? null,
                    'source_url' => $options['source_url'] ?? null,
                    'last_imported_at' => now(),
                ],
            );

            $groupIds = [];
            $variantCount = 0;

            foreach ($items as $item) {
                $groupId = $item['item_group_id'] !== '' ? $item['item_group_id'] : $item['id'];
                $groupTitle = $groupTitles[$groupId];
                $groupIds[$groupId] = true;

                $product = Product::updateOrCreate(
                    [
                        'feed_id' => $feed->id,
                        'product_id_external' => $item['id'],
                    ],
                    [
                        'retailer_id' => $retailerId,
                        'item_group_id' => $groupId,
                        'item_group_title' => $groupTitle,
                        'title' => $item['title'],
                        'description' => $item['description'] ?: null,
                        'brand' => $item['brand'] ?: null,
                        'product_type' => $item['product_type'] ?: null,
                        'google_product_category' => $item['google_product_category'] ?: null,
                        'link' => $item['link'] ?: null,
                        'image_link' => $item['image_link'] ?: null,
                        'price' => $item['price'],
                        'availability' => $item['availability'] ?: null,
                        'gtin' => $item['gtin'] ?: null,
                        'mpn' => $item['mpn'] ?: null,
                        'custom_labels' => $item['custom_labels'] ?: null,
                    ],
                );

                ProductConversationalAttribute::updateOrCreate(
                    ['product_id' => $product->id, 'attribute_type' => AttributeType::ItemGroupTitle->value],
                    ['retailer_id' => $retailerId, 'attribute_value' => ['value' => $groupTitle], 'source' => 'feed', 'live_in_feed' => true],
                );

                $variant = $this->variantOption($item, $groupTitle);
                if ($variant !== null) {
                    ProductConversationalAttribute::updateOrCreate(
                        ['product_id' => $product->id, 'attribute_type' => AttributeType::VariantOption->value],
                        ['retailer_id' => $retailerId, 'attribute_value' => ['option' => $variant], 'source' => 'feed', 'live_in_feed' => true],
                    );
                    $variantCount++;
                }
            }

            return [
                'feed_id' => $feed->id,
                'products' => count($items),
                'item_groups' => count($groupIds),
                'variant_options' => $variantCount,
            ];
        });
    }

    /**
     * @return array<int,array{id:string,item_group_id:string,title:string,description:string,brand:string,product_type:string,google_product_category:string,link:string,image_link:string,price:?float,availability:string,gtin:string,mpn:string,color:string,size:string,pattern:string,material:string,custom_labels:array<int,string>}>
     */
    protected function parse(string $content, string $format): array
    {
        $content = trim($content);
        $isXml = $format === 'xml' || ($format === 'auto' && str_starts_with($content, '<'));

        return $isXml ? $this->parseXml($content) : $this->parseTsv($content);
    }

    /** @return array<int,array<string,mixed>> */
    protected function parseXml(string $content): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException('Could not parse the feed as XML.');
        }

        $items = [];
        $nodes = $xml->channel->item ?? $xml->item ?? $xml->entry ?? [];

        foreach ($nodes as $node) {
            $g = $node->children('http://base.google.com/ns/1.0');

            $get = function (string $key) use ($node, $g): string {
                $value = (string) ($g->{$key} ?? '');
                if ($value === '') {
                    $value = (string) ($node->{$key} ?? '');
                }

                return trim($value);
            };

            $id = $get('id') !== '' ? $get('id') : $get('gtin');
            if ($id === '') {
                continue;
            }

            $customLabels = [];
            for ($i = 0; $i <= 4; $i++) {
                $label = $get('custom_label_'.$i);
                if ($label !== '') {
                    $customLabels[] = $label;
                }
            }

            $items[] = [
                'id' => $id,
                'item_group_id' => $get('item_group_id'),
                'title' => $get('title'),
                'description' => $get('description'),
                'brand' => $get('brand'),
                'product_type' => $get('product_type'),
                'google_product_category' => $get('google_product_category'),
                'link' => $get('link'),
                'image_link' => $get('image_link'),
                'price' => $this->price($get('price')),
                'availability' => $get('availability'),
                'gtin' => $get('gtin'),
                'mpn' => $get('mpn'),
                'color' => $get('color'),
                'size' => $get('size'),
                'pattern' => $get('pattern'),
                'material' => $get('material'),
                'custom_labels' => $customLabels,
            ];
        }

        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    protected function parseTsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return [];
        }

        $header = array_map(
            fn ($h) => Str::of($h)->lower()->replace([' ', '-'], '_')->trim()->value(),
            explode("\t", array_shift($lines)),
        );

        $items = [];
        foreach ($lines as $line) {
            $cells = explode("\t", $line);
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($cells[$i]) ? trim($cells[$i]) : '';
            }

            $id = $row['id'] ?? '';
            if ($id === '') {
                continue;
            }

            $customLabels = [];
            for ($i = 0; $i <= 4; $i++) {
                $label = $row['custom_label_'.$i] ?? '';
                if ($label !== '') {
                    $customLabels[] = $label;
                }
            }

            $items[] = [
                'id' => $id,
                'item_group_id' => $row['item_group_id'] ?? '',
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'brand' => $row['brand'] ?? '',
                'product_type' => $row['product_type'] ?? '',
                'google_product_category' => $row['google_product_category'] ?? '',
                'link' => $row['link'] ?? '',
                'image_link' => $row['image_link'] ?? '',
                'price' => $this->price($row['price'] ?? ''),
                'availability' => $row['availability'] ?? '',
                'gtin' => $row['gtin'] ?? '',
                'mpn' => $row['mpn'] ?? '',
                'color' => $row['color'] ?? '',
                'size' => $row['size'] ?? '',
                'pattern' => $row['pattern'] ?? '',
                'material' => $row['material'] ?? '',
                'custom_labels' => $customLabels,
            ];
        }

        return $items;
    }

    /** Pull a numeric price out of e.g. "699.00 GBP". */
    protected function price(string $raw): ?float
    {
        if ($raw === '' || ! preg_match('/[0-9]+(?:[.,][0-9]+)?/', $raw, $m)) {
            return null;
        }

        return (float) str_replace(',', '.', $m[0]);
    }

    /** Variant label from the Google variant attributes, falling back to the title remainder. */
    protected function variantOption(array $item, string $groupTitle): ?string
    {
        $parts = array_values(array_filter([
            $item['color'], $item['size'], $item['pattern'], $item['material'],
        ], fn ($v) => $v !== ''));

        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        // No explicit variant attributes: use what the title adds over the group title.
        if ($groupTitle !== '' && Str::startsWith($item['title'], $groupTitle)) {
            $remainder = trim(Str::after($item['title'], $groupTitle), ' -–—/|,');
            if ($remainder !== '' && mb_strlen($remainder) <= 40) {
                return $remainder;
            }
        }

        return null;
    }

    /** Longest common prefix of the group's product titles, cleaned to a sensible group label. */
    protected function groupTitle(array $titles): string
    {
        $titles = array_values(array_filter(array_map('trim', $titles), fn ($t) => $t !== ''));
        if ($titles === []) {
            return 'Untitled group';
        }
        if (count($titles) === 1) {
            return $titles[0];
        }

        $prefix = $titles[0];
        foreach ($titles as $title) {
            while ($prefix !== '' && ! Str::startsWith($title, $prefix)) {
                $prefix = mb_substr($prefix, 0, -1);
            }
            if ($prefix === '') {
                break;
            }
        }

        $prefix = trim($prefix, ' -–—/|,');

        // Too short a common prefix (e.g. just a brand) — fall back to the first title.
        return mb_strlen($prefix) >= 3 ? $prefix : $titles[0];
    }
}
