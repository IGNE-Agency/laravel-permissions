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

        $this->app->singleton(SubjectDiscovery::class);
        $this->app->bind(PermissionRegistry::class, static fn (): PermissionRegistry => PermissionRegistry::fromConfig());

        // An extender runs when the Gate is built, before any afterResolving callback, so the hook
        // comes before Spatie's and the app's own. Booting never resolves the Gate. The first-class
        // callable hands the Gate a Closure, which it reflects on to skip guests.
        $this->app->extend(Gate::class, static function (Gate $gate): Gate {
            $gate->before((new PermissionSpaceResolver($gate))->resolve(...));

            return $gate;
        });
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
