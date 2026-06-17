<?php

use Shoptimised\AiVisibility\Providers\BingSearchProvider;
use Shoptimised\AiVisibility\Providers\GeminiGroundedSearchProvider;
use Shoptimised\AiVisibility\Providers\ManualEvidenceProvider;
use Shoptimised\AiVisibility\Providers\OpenAiSearchProvider;
use Shoptimised\AiVisibility\Providers\PerplexitySearchProvider;

return [

    // The host application's authenticatable model. The package never ships a
    // User; it references this for createdBy / assignedTo relations and policies.
    'user_model' => env('AI_VISIBILITY_USER_MODEL', 'App\\Models\\User'),

    // Where the package routes are mounted in the host app.
    // Blade layout the reporting pages render inside. Point this at your
    // host layout (e.g. 'components.layouts.app') to inherit Shoptimised chrome.
    'layout' => env('AI_VISIBILITY_LAYOUT', 'ai-visibility::layouts.app'),

    'routing' => [
        'prefix' => env('AI_VISIBILITY_ROUTE_PREFIX', 'reports/ai-shopping-readiness'),
        'middleware' => ['web', 'auth', 'aiv.tenant'],
    ],

    'defaults' => [
        'country' => env('AI_VISIBILITY_DEFAULT_COUNTRY', 'GB'),
        'language' => env('AI_VISIBILITY_DEFAULT_LANGUAGE', 'en'),
    ],

    'limits' => [
        'max_item_groups_per_batch' => (int) env('AI_VISIBILITY_MAX_ITEM_GROUPS_PER_BATCH', 25),
        'max_prompts_per_batch' => (int) env('AI_VISIBILITY_MAX_PROMPTS_PER_BATCH', 250),
        'max_runs_per_prompt' => (int) env('AI_VISIBILITY_MAX_RUNS_PER_PROMPT', 3),
        'default_prompts_per_item_group' => 10,
    ],

    // When an item group has no Q&A in the feed, discover the FAQs buyers most
    // commonly ask (using the GTIN + item group title) via a search provider and
    // test them as qna_led prompts. Best-effort: failures fall back silently to
    // no Q&A prompts. 'platform' null => the first enabled non-manual provider.
    'faq_discovery' => [
        'enabled' => (bool) env('AI_VISIBILITY_FAQ_DISCOVERY', true),
        'platform' => env('AI_VISIBILITY_FAQ_DISCOVERY_PLATFORM'),
        'max_questions' => (int) env('AI_VISIBILITY_FAQ_DISCOVERY_MAX', 6),
    ],

    'monthly_batch_enabled' => (bool) env('AI_VISIBILITY_MONTHLY_BATCH_ENABLED', false),

    // How long a provider run job keeps retrying (covers rate-limit backoff).
    'rate_limit_retry_minutes' => (int) env('AI_VISIBILITY_RATE_LIMIT_RETRY_MINUTES', 30),

    'queues' => [
        'default' => env('AI_VISIBILITY_QUEUE_DEFAULT', 'default'),
        'ai' => env('AI_VISIBILITY_QUEUE_AI', 'ai-visibility'),
        'parsing' => env('AI_VISIBILITY_QUEUE_PARSING', 'parsing'),
    ],

    // Provider registry. The 'driver' maps a platform to a provider class.
    // API providers fall back to manual/semi-automated mode when no key is set.
    'providers' => [
        'manual' => [
            'driver' => ManualEvidenceProvider::class,
            'enabled' => true,
            'supports_citations' => true,
            'supports_screenshots' => true,
            'mode' => 'manual',
        ],
        'openai' => [
            'driver' => OpenAiSearchProvider::class,
            'enabled' => (bool) env('OPENAI_API_KEY'),
            'key' => env('OPENAI_API_KEY'),
            'model' => env('AI_VISIBILITY_OPENAI_MODEL', 'gpt-4o-search-preview'),
            'rate_limit_per_minute' => (int) env('AI_VISIBILITY_OPENAI_RPM', 10),
            'cost_per_million_tokens' => (float) env('AI_VISIBILITY_OPENAI_COST_PER_M', 5.0),
            'supports_citations' => true,
            'supports_screenshots' => false,
            'mode' => 'api',
        ],
        'gemini' => [
            'driver' => GeminiGroundedSearchProvider::class,
            'enabled' => (bool) env('GEMINI_API_KEY'),
            'key' => env('GEMINI_API_KEY'),
            'model' => env('AI_VISIBILITY_GEMINI_MODEL', 'gemini-2.5-flash'),
            'rate_limit_per_minute' => (int) env('AI_VISIBILITY_GEMINI_RPM', 10),
            'cost_per_million_tokens' => (float) env('AI_VISIBILITY_GEMINI_COST_PER_M', 0.30),
            'supports_citations' => true,
            'supports_screenshots' => false,
            'mode' => 'api',
        ],
        'perplexity' => [
            'driver' => PerplexitySearchProvider::class,
            'enabled' => (bool) env('PERPLEXITY_API_KEY'),
            'key' => env('PERPLEXITY_API_KEY'),
            'model' => env('AI_VISIBILITY_PERPLEXITY_MODEL', 'sonar'),
            'rate_limit_per_minute' => (int) env('AI_VISIBILITY_PERPLEXITY_RPM', 20),
            'cost_per_million_tokens' => (float) env('AI_VISIBILITY_PERPLEXITY_COST_PER_M', 1.0),
            'supports_citations' => true,
            'supports_screenshots' => false,
            'mode' => 'api',
        ],
        'bing' => [
            'driver' => BingSearchProvider::class,
            'enabled' => (bool) env('BING_SEARCH_API_KEY'),
            'key' => env('BING_SEARCH_API_KEY'),
            'rate_limit_per_minute' => (int) env('AI_VISIBILITY_BING_RPM', 10),
            'cost_per_million_tokens' => (float) env('AI_VISIBILITY_BING_COST_PER_M', 0.0),
            'supports_citations' => true,
            'supports_screenshots' => false,
            'mode' => 'api',
        ],
    ],
];
