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
        // The package policies type-hint the host's App\Models\User. That class
        // doesn't exist under test, so alias it to the stub to satisfy the hints.
        if (! class_exists('App\\Models\\User', false)) {
            class_alias(User::class, 'App\\Models\\User');
        }

        $app['config']->set('app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF/0rZhukChGI=');
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

        // spatie/laravel-permission ships its schema as a .stub, which a migration
        // path won't pick up. Run it directly so policy checks (which call
        // $user->can(...)) resolve against real permission tables under test.
        $permissionMigration = require dirname(__DIR__, 4)
            .'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
        $permissionMigration->up();
    }
}
