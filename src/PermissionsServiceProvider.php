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

        // An extender runs when the Gate is built, before any afterResolving callback, so these hooks
        // come before Spatie's: the access rule can only deny by answering before Spatie grants.
        // Booting never resolves the Gate. The first-class callables hand the Gate Closures, which
        // it reflects on to skip guests.
        $this->app->extend(Gate::class, static function (Gate $gate): Gate {
            $gate->before((new SpaceAccessRule($gate))->resolve(...));
            $gate->before((new PermissionSpaceResolver($gate))->resolve(...));
            $gate->before((new ImpliedActionResolver($gate))->resolve(...));

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
