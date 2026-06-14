<?php

namespace Shoptimised\AiVisibility\Providers;

use Shoptimised\AiVisibility\DataObjects\AiProviderResponse;
use Shoptimised\AiVisibility\Providers\Contracts\AiVisibilityProviderInterface;

/**
 * Default / fallback provider. Produces a "pending manual" response so a batch
 * can complete and surface item groups awaiting human evidence capture. Also
 * used to represent any API platform that has no key configured yet
 * (semi-automated evidence mode).
 */
class ManualEvidenceProvider implements AiVisibilityProviderInterface
{
    public function __construct(protected array $config = ['name' => 'manual']) {}

    public function runPrompt(string $prompt, array $context = []): AiProviderResponse
    {
        return AiProviderResponse::pendingManual(
            platform: $this->getName(),
            model: 'manual-evidence',
        );
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'manual';
    }

    public function supportsCitations(): bool
    {
        return (bool) ($this->config['supports_citations'] ?? true);
    }

    public function supportsScreenshots(): bool
    {
        return (bool) ($this->config['supports_screenshots'] ?? true);
    }
}
