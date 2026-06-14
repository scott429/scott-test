<?php

namespace Shoptimised\AiVisibility\DataObjects;

/**
 * Normalised result of a single provider run. Providers must return one of
 * these; the parser (Phase 3) consumes raw + text + citations.
 */
final readonly class AiProviderResponse
{
    /**
     * @param  Citation[]  $citations
     */
    public function __construct(
        public string $platform,
        public string $text = '',
        public array $raw = [],
        public array $citations = [],
        public bool $success = true,
        public string $mode = 'api',           // api | manual | pending
        public ?string $modelOrSurface = null,
        public ?string $error = null,
        public ?float $costUsd = null,
    ) {}

    public static function pendingManual(string $platform, ?string $model = null): self
    {
        return new self(
            platform: $platform,
            text: '',
            mode: 'pending',
            success: true,
            modelOrSurface: $model,
        );
    }

    public static function failed(string $platform, string $error, ?string $model = null): self
    {
        return new self(
            platform: $platform,
            success: false,
            mode: 'api',
            modelOrSurface: $model,
            error: $error,
        );
    }

    public function citationsToArray(): array
    {
        return array_map(fn (Citation $c) => $c->toArray(), $this->citations);
    }
}
