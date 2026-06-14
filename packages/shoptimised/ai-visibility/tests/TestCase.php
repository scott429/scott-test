<?php

namespace Shoptimised\AiVisibility\Tests;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Shoptimised\AiVisibility\AiVisibilityServiceProvider;
use Shoptimised\AiVisibility\Tests\Stubs\User;
use Spatie\Permission\PermissionServiceProvider;

use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            PermissionServiceProvider::class,
            class_exists(LivewireServiceProvider::class) ? LivewireServiceProvider::class : null,
            AiVisibilityServiceProvider::class,
        ]));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('ai_visibility.user_model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Register the framework tables (users, cache, jobs) as a migration path so
        // they run in the SAME migrate:fresh pass as the package migrations, sorted
        // by name. This matters because the package migration
        // 2026_06_01_000002_add_tenant_fields_to_users_table alters `users`, which
        // must already exist. A separate loadLaravelMigrations() pass would be
        // dropped by RefreshDatabase's migrate:fresh before the package migrations run.
        $this->loadMigrationsFrom(default_migration_path());
    }
}
