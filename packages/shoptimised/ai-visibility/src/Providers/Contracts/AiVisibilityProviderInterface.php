<?php

namespace Shoptimised\AiVisibility\Providers\Contracts;

use Shoptimised\AiVisibility\DataObjects\AiProviderResponse;

interface AiVisibilityProviderInterface
{
    /**
     * Run a single prompt against the provider.
     *
     * @param  array{country?:string,language?:string,retailer_domain?:string,item_group_title?:string,run_number?:int}  $context
     */
    public function runPrompt(string $prompt, array $context = []): AiProviderResponse;

    /** The platform key this provider answers to (openai, gemini, ...). */
    public function getName(): string;

    public function supportsCitations(): bool;

    public function supportsScreenshots(): bool;
}
