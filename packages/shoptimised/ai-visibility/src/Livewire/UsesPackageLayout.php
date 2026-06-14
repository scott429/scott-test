<?php

namespace Shoptimised\AiVisibility\Livewire;

/**
 * Shared layout resolution so every page renders inside the host's chrome.
 * Override the layout via config('ai_visibility.layout').
 */
trait UsesPackageLayout
{
    protected function layoutName(): string
    {
        return (string) config('ai_visibility.layout', 'ai-visibility::layouts.app');
    }
}
