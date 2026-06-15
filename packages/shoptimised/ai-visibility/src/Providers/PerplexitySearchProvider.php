<?php

namespace Shoptimised\AiVisibility\Providers;

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\DataObjects\AiProviderResponse;
use Shoptimised\AiVisibility\DataObjects\Citation;

/**
 * Real provider: Perplexity Sonar. Returns a grounded answer plus direct source
 * URLs (cleaner than Gemini's redirect citations), which map straight into
 * AiProviderResponse citations with reliable domains.
 */
class PerplexitySearchProvider extends AbstractApiProvider
{
    private const ENDPOINT = 'https://api.perplexity.ai/chat/completions';

    public function getName(): string
    {
        return 'perplexity';
    }

    public function runPrompt(string $prompt, array $context = []): AiProviderResponse
    {
        $key = $this->config['key'] ?? null;
        if (empty($key)) {
            return AiProviderResponse::pendingManual($this->getName(), $this->config['model'] ?? null);
        }

        $model = $this->config['model'] ?? 'sonar';
        $region = strtoupper((string) ($context['country'] ?? 'GB'));

        try {
            $response = Http::timeout(60)
                ->retry(2, 1000, throw: false)
                ->withToken($key)
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => "You are a shopping assistant for online shoppers in {$region}. Recommend specific retailers and products, and cite your sources."],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if ($response->failed()) {
                return AiProviderResponse::failed(
                    $this->getName(),
                    'Perplexity HTTP '.$response->status().': '.$response->body(),
                    $model,
                );
            }

            return $this->mapResponse($response->json(), $model);
        } catch (\Throwable $e) {
            return AiProviderResponse::failed($this->getName(), $e->getMessage(), $model);
        }
    }

    /** @param  array<string,mixed>  $body */
    protected function mapResponse(array $body, string $model): AiProviderResponse
    {
        $text = (string) data_get($body, 'choices.0.message.content', '');

        // Newer responses expose search_results [{title,url}]; older ones a flat
        // citations [url, ...]. Support both.
        $citations = [];
        $position = 0;

        foreach ($body['search_results'] ?? [] as $result) {
            $url = $result['url'] ?? null;
            if (! $url) {
                continue;
            }
            $position++;
            $citations[] = new Citation($position, $url, $result['title'] ?? null, $this->domainOf($url));
        }

        if ($citations === []) {
            foreach ($body['citations'] ?? [] as $url) {
                if (! is_string($url) || $url === '') {
                    continue;
                }
                $position++;
                $citations[] = new Citation($position, $url, null, $this->domainOf($url));
            }
        }

        return new AiProviderResponse(
            platform: $this->getName(),
            text: $text,
            raw: $body,
            citations: $citations,
            success: true,
            mode: 'api',
            modelOrSurface: $model,
        );
    }

    protected function domainOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: null;

        return $host ? mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host) : null;
    }
}
