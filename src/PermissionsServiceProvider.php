<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

final class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::configFile(), 'permissions');

        $this->app->bind(PermissionRegistry::class, static fn (): PermissionRegistry => PermissionRegistry::fromConfig());
    }

    public function boot(): void
    {
        $this->publishes([
            self::configFile() => $this->app->configPath('permissions.php'),
        ], 'permissions-config');

        // Registered once the Gate is resolved, like Spatie's hook, so booting never resolves it.
        // The first-class callable hands the Gate a Closure, which it reflects on to skip guests.
        $this->callAfterResolving(Gate::class, static function (Gate $gate): void {
            $gate->before((new PermissionSpaceResolver($gate))->resolve(...));
        });
    }

    private static function configFile(): string
    {
        return dirname(__DIR__).'/config/permissions.php';
    }
}
