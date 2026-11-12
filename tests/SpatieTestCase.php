<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Tests\Fixtures\TestUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Runs the fixture enums against a real Spatie install: Laravel's and Spatie's tables on the
 * in-memory database, every name of permissions()->all() seeded for the web guard.
 */
#[WithConfig('database.default', 'testing')]
#[WithConfig('cache.default', 'array')]
#[WithConfig('auth.providers.users.model', TestUser::class)]
abstract class SpatieTestCase extends PermissionTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PermissionServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->migrateSpatieTables();

        foreach (permissions()->all() as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function user(string ...$permissions): TestUser
    {
        $user = TestUser::create([
            'name' => 'Test user',
            'email' => uniqid('user', true).'@example.test',
            'password' => 'secret',
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo(...$permissions);
        }

        return $user;
    }

    private function migrateSpatieTables(): void
    {
        $migration = require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';

        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            throw new RuntimeException('The Spatie migration stub did not return a migration with an up() method.');
        }

        $migration->up();
    }
}
