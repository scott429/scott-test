<?php

namespace Shoptimised\AiVisibility\DataObjects;

final readonly class CompetitorMention
{
    public function __construct(
        public string $domain,
        public ?string $name = null,
        public ?string $url = null,
        public ?string $title = null,
        public ?int $mentionPosition = null,
        public ?int $citationPosition = null,
    ) {}

    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'name' => $this->name,
            'url' => $this->url,
            'title' => $this->title,
            'mention_position' => $this->mentionPosition,
            'citation_position' => $this->citationPosition,
        ];
    }
}
