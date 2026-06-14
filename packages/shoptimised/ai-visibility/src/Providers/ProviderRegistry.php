<?php

namespace Shoptimised\AiVisibility\Providers;

use Shoptimised\AiVisibility\Providers\Contracts\AiVisibilityProviderInterface;

/**
 * Resolves a platform key to a provider. Business logic asks the registry, never
 * a concrete provider class — keeping the provider layer swappable.
 */
class ProviderRegistry
{
    /** @param  array<string,array>  $config  config('ai_visibility.providers') */
    public function __construct(protected array $config) {}

    public function resolve(string $platform): AiVisibilityProviderInterface
    {
        $cfg = $this->config[$platform] ?? null;

        if ($cfg && ($cfg['enabled'] ?? false) && ! empty($cfg['driver'])) {
            $driver = $cfg['driver'];

            return new $driver(array_merge($cfg, ['name' => $platform]));
        }

        // No key / not enabled: represent this platform in manual evidence mode.
        return new ManualEvidenceProvider([
            'name' => $platform,
            'supports_citations' => $cfg['supports_citations'] ?? true,
            'supports_screenshots' => $cfg['supports_screenshots'] ?? true,
        ]);
    }

    /** @return string[] */
    public function enabledPlatforms(): array
    {
        return array_keys(array_filter(
            $this->config,
            fn ($cfg) => (bool) ($cfg['enabled'] ?? false)
        ));
    }

    public function isEnabled(string $platform): bool
    {
        return (bool) ($this->config[$platform]['enabled'] ?? false);
    }

    /** @return string[] all known platform keys */
    public function platforms(): array
    {
        return array_keys($this->config);
    }
}
