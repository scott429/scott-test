<?php

namespace Shoptimised\AiVisibility\Providers;

class OpenAiSearchProvider extends AbstractApiProvider
{
    public function getName(): string
    {
        return 'openai';
    }
}
