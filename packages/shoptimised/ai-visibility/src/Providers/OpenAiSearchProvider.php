<?php

namespace Shoptimised\AiVisibility\Providers;

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\DataObjects\AiProviderResponse;
use Shoptimised\AiVisibility\DataObjects\Citation;

/**
 * Real provider: OpenAI web search (chat/completions with a *-search-preview
 * model). Returns the answer text plus url_citation annotations, mapped into
 * AiProviderResponse citations with direct-URL domains.
 */
class OpenAiSearchProvider extends AbstractApiProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function getName(): string
    {
        return 'openai';
    }

    public function runPrompt(string $prompt, array $context = []): AiProviderResponse
    {
        $key = $this->config['key'] ?? null;
        if (empty($key)) {
            return AiProviderResponse::pendingManual($this->getName(), $this->config['model'] ?? null);
        }

        $model = $this->config['model'] ?? 'gpt-4o-search-preview';
        $region = strtoupper((string) ($context['country'] ?? 'GB'));

        try {
            $response = Http::timeout(60)
                ->retry(2, 1000, throw: false)
                ->withToken($key)
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'web_search_options' => (object) [],
                    'messages' => [
                        ['role' => 'system', 'content' => "You are a shopping assistant for online shoppers in {$region}. Recommend specific retailers and products, and cite your sources."],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if ($response->failed()) {
                return AiProviderResponse::failed(
                    $this->getName(),
                    'OpenAI HTTP '.$response->status().': '.$response->body(),
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
        $message = $body['choices'][0]['message'] ?? [];
        $text = (string) ($message['content'] ?? '');

        $citations = [];
        $position = 0;
        foreach ($message['annotations'] ?? [] as $annotation) {
            if (($annotation['type'] ?? null) !== 'url_citation') {
                continue;
            }
            $cite = $annotation['url_citation'] ?? [];
            $url = $cite['url'] ?? null;
            if (! $url) {
                continue;
            }
            $position++;
            $citations[] = new Citation($position, $url, $cite['title'] ?? null, $this->domainOf($url));
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
