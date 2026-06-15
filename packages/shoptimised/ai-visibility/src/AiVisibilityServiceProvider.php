<?php

namespace Shoptimised\AiVisibility;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Shoptimised\AiVisibility\Console\Commands\ImportFeedCommand;
use Shoptimised\AiVisibility\Http\Middleware\SetTenant;
use Shoptimised\AiVisibility\Livewire\BatchProgressPage;
use Shoptimised\AiVisibility\Livewire\BatchResultsPage;
use Shoptimised\AiVisibility\Livewire\ItemGroupDetailPage;
use Shoptimised\AiVisibility\Livewire\LandingPage;
use Shoptimised\AiVisibility\Livewire\NewCheckPage;
use Shoptimised\AiVisibility\Livewire\RecommendationsPage;
use Shoptimised\AiVisibility\Models\AiVisibilityBatch;
use Shoptimised\AiVisibility\Models\FeedActionRecommendation;
use Shoptimised\AiVisibility\Policies\AiVisibilityBatchPolicy;
use Shoptimised\AiVisibility\Policies\FeedActionRecommendationPolicy;
use Shoptimised\AiVisibility\Providers\ProviderRegistry;
use Shoptimised\AiVisibility\Support\TenantContext;

class AiVisibilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai_visibility.php', 'ai_visibility');

        // Active-tenant holder shared by the scope, middleware and jobs.
        $this->app->singleton(TenantContext::class);

        // Swappable provider layer.
        $this->app->singleton(ProviderRegistry::class, function ($app) {
            return new ProviderRegistry($app['config']->get('ai_visibility.providers', []));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Per-provider rate limiters, keyed by platform. RunVisibilityPromptJob
        // applies the matching limiter so each platform throttles independently
        // (defaults to the cache store locally; Valkey on Laravel Cloud).
        foreach ((array) $this->app['config']->get('ai_visibility.providers', []) as $platform => $cfg) {
            $rpm = (int) ($cfg['rate_limit_per_minute'] ?? 0);
            if ($rpm > 0) {
                RateLimiter::for("aiv-{$platform}", fn () => Limit::perMinute($rpm)->by("aiv-{$platform}"));
            }
        }

        $this->app['router']->aliasMiddleware('aiv.tenant', SetTenant::class);

        Gate::policy(AiVisibilityBatch::class, AiVisibilityBatchPolicy::class);
        Gate::policy(FeedActionRecommendation::class, FeedActionRecommendationPolicy::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-visibility');
        // Resolve <x-aiv::*> to the package's view namespace (e.g. the
        // ai-visibility::components.methodology-note view). The directory must be a
        // view path/namespace, not an absolute filesystem path.
        Blade::anonymousComponentNamespace('ai-visibility::components', 'aiv');

        if (class_exists(Livewire::class)) {
            Livewire::component('aiv.landing', LandingPage::class);
            Livewire::component('aiv.new-check', NewCheckPage::class);
            Livewire::component('aiv.batch-progress', BatchProgressPage::class);
            Livewire::component('aiv.batch-results', BatchResultsPage::class);
            Livewire::component('aiv.item-group-detail', ItemGroupDetailPage::class);
            Livewire::component('aiv.recommendations', RecommendationsPage::class);
        }

        $routing = $this->app['config']->get('ai_visibility.routing');
        Route::group([
            'prefix' => $routing['prefix'] ?? 'reports/ai-shopping-readiness',
            'middleware' => $routing['middleware'] ?? ['web', 'auth', 'aiv.tenant'],
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportFeedCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/ai_visibility.php' => config_path('ai_visibility.php'),
            ], 'ai-visibility-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-visibility-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ai-visibility'),
            ], 'ai-visibility-views');
        }
    }
}
