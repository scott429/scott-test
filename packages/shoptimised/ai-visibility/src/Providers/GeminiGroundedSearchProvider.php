<?php

namespace Shoptimised\AiVisibility\Providers;

use Illuminate\Support\Facades\Http;
use Shoptimised\AiVisibility\DataObjects\AiProviderResponse;
use Shoptimised\AiVisibility\DataObjects\Citation;
use Shoptimised\AiVisibility\Providers\Exceptions\TransientProviderException;

/**
 * Real provider: Google Gemini with Search grounding. Sends the prompt with the
 * google_search tool enabled and maps the grounded answer + groundingMetadata
 * into an AiProviderResponse (text + citation URLs).
 *
 * Note: Gemini returns grounding citations as redirect URIs; the source domain
 * is taken from each chunk's title when it looks like a domain, otherwise from
 * the URI host. Competitors are still only extracted from these real citations.
 */
class GeminiGroundedSearchProvider extends AbstractApiProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function getName(): string
    {
        return 'gemini';
    }

    public function runPrompt(string $prompt, array $context = []): AiProviderResponse
    {
        $key = $this->config['key'] ?? null;
        if (empty($key)) {
            return AiProviderResponse::pendingManual($this->getName(), $this->config['model'] ?? null);
        }

        $model = $this->config['model'] ?? 'gemini-2.5-flash';
        $region = strtoupper((string) ($context['country'] ?? 'GB'));

        try {
            $response = Http::timeout(60)
                ->retry(2, 1000, throw: false)
                ->withHeaders(['x-goog-api-key' => $key])
                ->post(self::ENDPOINT."/{$model}:generateContent", [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $this->wrapPrompt($prompt, $region)]],
                    ]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => ['temperature' => 0.2],
                ]);

            if ($response->failed()) {
                if ($response->status() === 429 || $response->serverError()) {
                    throw new TransientProviderException('Gemini HTTP '.$response->status());
                }

                return AiProviderResponse::failed(
                    $this->getName(),
                    'Gemini HTTP '.$response->status().': '.$response->body(),
                    $model,
                );
            }

            return $this->mapResponse($response->json(), $model);
        } catch (TransientProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return AiProviderResponse::failed($this->getName(), $e->getMessage(), $model);
        }
    }

    protected function wrapPrompt(string $prompt, string $region): string
    {
        return "{$prompt}\n\n(Answer for online shoppers in {$region}. "
            .'Name specific retailers and products you would recommend, and cite the sources you used.)';
    }

    /** @param  array<string,mixed>  $body */
    protected function mapResponse(array $body, string $model): AiProviderResponse
    {
        $candidate = $body['candidates'][0] ?? [];

        $text = collect($candidate['content']['parts'] ?? [])
            ->pluck('text')
            ->filter()
            ->implode("\n");

        $citations = [];
        $chunks = $candidate['groundingMetadata']['groundingChunks'] ?? [];
        $position = 0;
        foreach ($chunks as $chunk) {
            $web = $chunk['web'] ?? null;
            if (! $web || empty($web['uri'])) {
                continue;
            }
            $position++;
            $citations[] = new Citation(
                position: $position,
                url: $web['uri'],
                title: $web['title'] ?? null,
                domain: $this->domainFrom($web['title'] ?? null, $web['uri']),
            );
        }

        $tokens = ((int) data_get($body, 'usageMetadata.totalTokenCount', 0)) ?: null;

        return new AiProviderResponse(
            platform: $this->getName(),
            text: $text,
            raw: $body,
            citations: $citations,
            success: true,
            mode: 'api',
            modelOrSurface: $model,
            costUsd: $this->estimateCost($tokens),
            totalTokens: $tokens,
        );
    }

    /** Gemini's chunk title is usually the source domain; fall back to the URI host. */
    protected function domainFrom(?string $title, string $uri): ?string
    {
        $title = $title !== null ? trim($title) : '';
        if ($title !== '' && ! str_contains($title, ' ') && str_contains($title, '.')) {
            return mb_strtolower(preg_replace('/^www\./', '', $title) ?? $title);
        }

        $host = parse_url($uri, PHP_URL_HOST) ?: null;

        return $host ? mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host) : null;
    }
}
