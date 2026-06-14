<?php

namespace Shoptimised\AiVisibility\Providers;

use Shoptimised\AiVisibility\DataObjects\AiProviderResponse;
use Shoptimised\AiVisibility\Providers\Contracts\AiVisibilityProviderInterface;

/**
 * Shared behaviour for API-backed providers. Concrete HTTP integration lands in
 * a later phase; for now runPrompt() degrades to a pending-manual response so
 * the pipeline runs end-to-end without external calls. Override callApi() to
 * implement the real request when wiring each provider up.
 */
abstract class AbstractApiProvider implements AiVisibilityProviderInterface
{
    public function __construct(protected array $config = []) {}

    abstract public function getName(): string;

    public function runPrompt(string $prompt, array $context = []): AiProviderResponse
    {
        if (empty($this->config['key'])) {
            return AiProviderResponse::pendingManual($this->getName(), $this->config['model'] ?? null);
        }

        // TODO (provider integration phase): perform the real search/browse call
        // and map the response into AiProviderResponse with text + citations.
        return AiProviderResponse::pendingManual($this->getName(), $this->config['model'] ?? null);
    }

    public function supportsCitations(): bool
    {
        return (bool) ($this->config['supports_citations'] ?? true);
    }

    public function supportsScreenshots(): bool
    {
        return (bool) ($this->config['supports_screenshots'] ?? false);
    }
}
