<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Illuminate\Support\ServiceProvider;

final class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::configFile(), 'permissions');

        $this->app->singleton(SubjectDiscovery::class);
    }

    public function boot(): void
    {
        $this->publishes([
            self::configFile() => $this->app->configPath('permissions.php'),
        ], 'permissions-config');
    }

    private static function configFile(): string
    {
        return dirname(__DIR__).'/config/permissions.php';
    }
}
