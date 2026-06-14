<?php

namespace Shoptimised\AiVisibility\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Shoptimised\AiVisibility\Filament\Resources\AiVisibilityBatchResource;
use Shoptimised\AiVisibility\Filament\Resources\FeedActionRecommendationResource;

/**
 * Opt-in internal admin for Shoptimised staff. Add to a Filament panel:
 *
 *   use Shoptimised\AiVisibility\Filament\AiVisibilityPlugin;
 *   $panel->plugin(AiVisibilityPlugin::make());
 *
 * Targets Filament v4. If you're on a different major, regenerate the resources
 * with `php artisan make:filament-resource` and keep the models/columns below.
 */
class AiVisibilityPlugin implements Plugin
{
    public function getId(): string
    {
        return 'ai-visibility';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            AiVisibilityBatchResource::class,
            FeedActionRecommendationResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
