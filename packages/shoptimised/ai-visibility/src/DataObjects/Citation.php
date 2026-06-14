<?php

namespace Shoptimised\AiVisibility\DataObjects;

final readonly class Citation
{
    public function __construct(
        public int $position,
        public ?string $url = null,
        public ?string $title = null,
        public ?string $domain = null,
    ) {}

    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'url' => $this->url,
            'title' => $this->title,
            'domain' => $this->domain,
        ];
    }
}
